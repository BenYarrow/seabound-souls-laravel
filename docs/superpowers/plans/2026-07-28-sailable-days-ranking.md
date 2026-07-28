# Sailable-days Ranking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rank destination spots by the *typical* number of sailable days in a chosen month for a user-set minimum wind speed, reordering the destination cards live and driving the charts, entirely client-side.

**Architecture:** A new `spot_sailable_days` daily layer (one row per spot per day, storing the day's 2nd-windiest 9am–7pm hour in kts) is populated by extending the existing `WeatherFetcher`. `DestinationController` ships that data pooled by month plus a cross-year-averaged "typical year" climate map. The React page counts days ≥ threshold and ranks in the browser, with all filter state mirrored to the URL for shareable links.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Recharts 3, react-select 5, Tailwind 3. PHP tests: PHPUnit on in-memory SQLite. JS tests: Vitest (Node 22).

## Global Constraints

- **Node:** use v22+ for any `npm`/`vite`/`vitest` command — `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"` first. System `node` is v14 and will fail.
- **PHP tests** run on in-memory SQLite (`php artisan test`, no Postgres needed). Dev/prod are Postgres — smoke-test aggregate/grouping queries on real Postgres before merge.
- **JS tests:** `npm run test:js` (Vitest, Node 22).
- **TDD:** write the failing test first, watch it fail, implement minimally, watch it pass, commit.
- **JSDoc on every TS/TSX function; PHPDoc on non-obvious PHP methods. Module header comment at the top of each new source file.** No single-letter variables (except `i` in a trivial loop), no cryptic abbreviations.
- **Dark mode + responsive:** every changed screen must work in light and dark and at mobile/tablet/desktop. Use the project's semantic Tailwind tokens (`bg-cream`, `text-primary`, `text-secondary`, etc.) — no raw `bg-white`/`text-gray-*` that won't flip. (Existing components on this page use `bg-white`/`text-gray-*`; match the immediate surroundings, and do not introduce *new* banned utilities.)
- **Sustained wind, not gusts,** drives the sailable test. The fixed rule is **≥2 hours ≥ minimum**, encoded as "the day's 2nd-highest sailing-window hour ≥ minimum".
- **Branch:** `sailable-days-ranking` (already cut). No direct commits to `main`.

---

### Task 1: `spot_sailable_days` table, `SailableDay` model, factory, relation

**Files:**
- Create: `database/migrations/2026_07_28_100000_create_spot_sailable_days_table.php`
- Create: `app/Models/SailableDay.php`
- Create: `database/factories/SailableDayFactory.php`
- Modify: `app/Models/SpotGuide.php` (add `sailableDays()` relation near `weatherRecords()` at line 216)
- Test: `tests/Feature/SailableDayModelTest.php`

**Interfaces:**
- Produces: table `spot_sailable_days(id, spot_guide_id, date, year, month, qualifying_wind_kts, timestamps)` unique `(spot_guide_id, date)`; `App\Models\SailableDay` with `$fillable = ['spot_guide_id','date','year','month','qualifying_wind_kts']`, cast `date` → `date:Y-m-d`, `qualifying_wind_kts` → `decimal:1`, `spotGuide()` belongsTo; `SpotGuide::sailableDays(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/SailableDayModelTest.php

namespace Tests\Feature;

use App\Models\SailableDay;
use App\Models\SpotGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SailableDayModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_spot_guide_has_many_sailable_days(): void
    {
        $spot = SpotGuide::factory()->create();
        SailableDay::factory()->for($spot)->create([
            'date' => '2025-08-01', 'year' => 2025, 'month' => 8, 'qualifying_wind_kts' => 21.4,
        ]);

        $this->assertCount(1, $spot->refresh()->sailableDays);
        $this->assertSame('21.4', (string) $spot->sailableDays->first()->qualifying_wind_kts);
        $this->assertSame(8, $spot->sailableDays->first()->month);
    }

    public function test_date_and_spot_are_unique_together(): void
    {
        $spot = SpotGuide::factory()->create();
        SailableDay::factory()->for($spot)->create(['date' => '2025-08-01', 'year' => 2025, 'month' => 8]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        SailableDay::factory()->for($spot)->create(['date' => '2025-08-01', 'year' => 2025, 'month' => 8]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SailableDayModelTest`
Expected: FAIL — class `App\Models\SailableDay` not found.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_07_28_100000_create_spot_sailable_days_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spot_sailable_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_guide_id')->constrained('spot_guides')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            // The day's 2nd-highest sailing-window (9am-7pm) sustained-wind hour, in kts.
            // A day is "sailable" at minimum X iff this value >= X (>= 2 hours at/above X).
            $table->decimal('qualifying_wind_kts', 5, 1);
            $table->timestamps();

            $table->unique(['spot_guide_id', 'date']);
            $table->index(['spot_guide_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_sailable_days');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

// One day of sailable-wind data for a spot guide: the 2nd-highest sustained-wind
// hour within the 9am-7pm sailing window, in knots. A day counts as "sailable"
// at a chosen minimum X when qualifying_wind_kts >= X (i.e. at least 2 hours blew
// at or above X). Feeds the client-side sailable-days ranking on /destinations.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SailableDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'spot_guide_id', 'date', 'year', 'month', 'qualifying_wind_kts',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'qualifying_wind_kts' => 'decimal:1',
    ];

    public function spotGuide(): BelongsTo
    {
        return $this->belongsTo(SpotGuide::class);
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php

namespace Database\Factories;

use App\Models\SpotGuide;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\SailableDay> */
class SailableDayFactory extends Factory
{
    public function definition(): array
    {
        $date = $this->faker->dateTimeBetween('-3 years', 'now');

        return [
            'spot_guide_id' => SpotGuide::factory(),
            'date' => $date->format('Y-m-d'),
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
            'qualifying_wind_kts' => $this->faker->randomFloat(1, 0, 40),
        ];
    }
}
```

- [ ] **Step 6: Add the relation to `SpotGuide`**

Insert immediately after the `weatherRecords()` method (after line 219):

```php
    /** Daily sailable-wind data (2nd-highest sailing-window hour, kts); feeds the days-per-month ranking. */
    public function sailableDays(): HasMany
    {
        return $this->hasMany(SailableDay::class);
    }
```

- [ ] **Step 7: Run the test to verify it passes**

Run: `php artisan test --filter=SailableDayModelTest`
Expected: PASS (2 tests).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_07_28_100000_create_spot_sailable_days_table.php app/Models/SailableDay.php database/factories/SailableDayFactory.php app/Models/SpotGuide.php tests/Feature/SailableDayModelTest.php
git commit -m "feat: spot_sailable_days table, model and relation"
```

---

### Task 2: Extend `WeatherFetcher` to persist daily sailable-wind

**Files:**
- Modify: `app/Services/WeatherFetcher.php` (add daily upsert inside `fetchForSpot`, reusing the existing `$dailyMap`)
- Test: `tests/Feature/WeatherFetcherSailableDaysTest.php`

**Interfaces:**
- Consumes: existing `$dailyMap[$date]['winds']` (hourly sustained winds in kts within the 9am–7pm window, built at lines 71–92).
- Produces: one `spot_sailable_days` row per day with `qualifying_wind_kts` = the 2nd-highest value in that day's winds (0.0 if the day has fewer than 2 hourly readings). Monthly `weather_records` output is unchanged.

- [ ] **Step 1: Write the failing test**

The fetcher pulls 3-month windows; we fake every Open-Meteo call with two 9am/10am hourly readings for a single day. With winds `[18, 22]` the 2nd-highest is 18.0.

```php
<?php
// tests/Feature/WeatherFetcherSailableDaysTest.php

namespace Tests\Feature;

use App\Models\SailableDay;
use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class WeatherFetcherSailableDaysTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_second_highest_sailing_window_hour_per_day(): void
    {
        Sleep::fake();

        // Every archive request returns the same single day: 09:00 -> 22kts, 10:00 -> 18kts.
        // (08:00 is outside the 9am-7pm window and must be ignored.)
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2025-08-01T08:00', '2025-08-01T09:00', '2025-08-01T10:00'],
                    'temperature_2m' => [20.0, 21.0, 22.0],
                    'wind_speed_10m' => [40.0, 22.0, 18.0],
                    'wind_gusts_10m' => [50.0, 30.0, 26.0],
                ],
            ]),
        ]);

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $day = SailableDay::where('spot_guide_id', $spot->id)->where('date', '2025-08-01')->first();
        $this->assertNotNull($day);
        // 2nd-highest of the in-window winds [22, 18] is 18.0 (08:00's 40 is excluded).
        $this->assertSame('18.0', (string) $day->qualifying_wind_kts);
        $this->assertSame(2025, $day->year);
        $this->assertSame(8, $day->month);
    }

    public function test_a_day_with_a_single_in_window_hour_scores_zero(): void
    {
        Sleep::fake();

        Http::fake([
            'archive-api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2025-08-02T09:00'],
                    'temperature_2m' => [21.0],
                    'wind_speed_10m' => [30.0],
                    'wind_gusts_10m' => [40.0],
                ],
            ]),
        ]);

        $spot = SpotGuide::factory()->create(['latitude' => 1, 'longitude' => 1]);
        app(WeatherFetcher::class)->fetchForSpot($spot);

        $day = SailableDay::where('spot_guide_id', $spot->id)->where('date', '2025-08-02')->first();
        $this->assertNotNull($day);
        $this->assertSame('0.0', (string) $day->qualifying_wind_kts); // <2 hours => never sailable
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WeatherFetcherSailableDaysTest`
Expected: FAIL — no `spot_sailable_days` rows written (assertNotNull fails).

- [ ] **Step 3: Add the daily upsert to `WeatherFetcher::fetchForSpot`**

Add `use App\Models\SailableDay;` to the imports (after line 11). Then insert this block **after** the year/month upsert loop that ends at line 125 (still inside `fetchForSpot`, after the `WeatherRecord::updateOrCreate` foreach closes):

```php
        // Persist the daily sailable-wind layer from the same 9am-7pm buckets.
        // qualifying_wind_kts = the day's 2nd-highest sustained-wind hour, because
        // "sailable" means >= 2 hours at/above the user's minimum, i.e. the 2nd
        // hour (when winds are sorted high-to-low) already clears it. A day with
        // fewer than 2 in-window readings can never be sailable, so it scores 0.
        foreach ($dailyMap as $date => $values) {
            $winds = $values['winds'];
            rsort($winds); // highest first
            $secondHighest = count($winds) >= 2 ? $winds[1] : 0.0;

            [$year, $monthNumber] = explode('-', $date);

            SailableDay::updateOrCreate(
                ['spot_guide_id' => $spot->id, 'date' => $date],
                [
                    'year' => (int) $year,
                    'month' => (int) $monthNumber,
                    'qualifying_wind_kts' => round($secondHighest, 1),
                ]
            );
        }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=WeatherFetcherSailableDaysTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full weather suite to confirm monthly records still work**

Run: `php artisan test --filter=WeatherFetcher`
Expected: PASS (all existing WeatherFetcher tests plus the two new ones).

- [ ] **Step 6: Commit**

```bash
git add app/Services/WeatherFetcher.php tests/Feature/WeatherFetcherSailableDaysTest.php
git commit -m "feat: WeatherFetcher persists daily sailable-wind layer"
```

---

### Task 3: `DestinationController` — ship `sailableDays` + `climate`

**Files:**
- Modify: `app/Http/Controllers/DestinationController.php`
- Test: `tests/Feature/DestinationSailablePayloadTest.php`

**Interfaces:**
- Produces two new Inertia props (replacing `weatherData`):
  - `sailableDays`: `{ [title: string]: { [month: number 1-12]: { values: number[], years: number } } }` — `values` = every day's `qualifying_wind_kts` for that spot+month pooled across all held years; `years` = distinct year count.
  - `climate`: `{ [title: string]: Array<{ month: string, avgTemp, ktsWind, ktsGust, mphWind, mphGust, kphWind, kphGust }> }` — one entry per month present, cross-year-averaged, sorted by month.
- Keeps: `spotGuides`, `featuredSpotGuide`, `showProvenance`, `static_masthead`, `meta`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/DestinationSailablePayloadTest.php

namespace Tests\Feature;

use App\Models\SailableDay;
use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationSailablePayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_pools_sailable_days_by_month_with_a_year_count(): void
    {
        $spot = SpotGuide::factory()->create(['title' => 'Vassiliki', 'is_published' => true]);

        // Two Augusts (2024, 2025), one day each.
        SailableDay::factory()->for($spot)->create(['date' => '2024-08-10', 'year' => 2024, 'month' => 8, 'qualifying_wind_kts' => 22.0]);
        SailableDay::factory()->for($spot)->create(['date' => '2025-08-11', 'year' => 2025, 'month' => 8, 'qualifying_wind_kts' => 12.0]);

        $response = $this->get('/destinations');

        $response->assertInertia(fn ($page) => $page
            ->where('sailableDays.Vassiliki.8.years', 2)
            ->where('sailableDays.Vassiliki.8.values', [22.0, 12.0])
        );
    }

    public function test_climate_averages_a_month_across_years(): void
    {
        $spot = SpotGuide::factory()->create(['title' => 'Tarifa', 'is_published' => true]);
        WeatherRecord::factory()->for($spot)->create(['year' => 2024, 'month' => 8, 'kts_wind' => 20.0, 'avg_temp' => 26.0, 'kts_gust' => 25.0, 'mph_wind' => 23, 'mph_gust' => 29, 'kph_wind' => 37, 'kph_gust' => 46]);
        WeatherRecord::factory()->for($spot)->create(['year' => 2025, 'month' => 8, 'kts_wind' => 22.0, 'avg_temp' => 28.0, 'kts_gust' => 27.0, 'mph_wind' => 25, 'mph_gust' => 31, 'kph_wind' => 41, 'kph_gust' => 50]);

        $response = $this->get('/destinations');

        // Aug ktsWind averages (20+22)/2 = 21.0; there is exactly one August entry.
        $response->assertInertia(fn ($page) => $page
            ->where('climate.Tarifa.0.month', 'August')
            ->where('climate.Tarifa.0.ktsWind', 21.0)
            ->where('climate.Tarifa.0.avgTemp', 27.0)
        );
    }
}
```

> Note: this test assumes a `WeatherRecordFactory` exists. If `php artisan test --filter=DestinationSailablePayloadTest` errors with "factory not found", create `database/factories/WeatherRecordFactory.php` mirroring `SailableDayFactory` (fillable columns from `WeatherRecord::$fillable`, sensible numeric defaults) as a first sub-step, then continue.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=DestinationSailablePayloadTest`
Expected: FAIL — props `sailableDays`/`climate` absent.

- [ ] **Step 3: Eager-load `sailableDays` and build the new props**

In `DestinationController::index`, add `sailableDays` to the eager-load on line 35:

```php
        $spotGuides = SpotGuide::published()
            ->with(['country', 'thumbnailMedia', 'weatherRecords', 'sailableDays', 'author.profileImageMedia'])
            ->orderBy('title')
            ->get();
```

Replace the `$weatherData` block (lines 68–83) with the two new builders:

```php
        // Pooled daily sailable-wind values, keyed by title then month (1-12).
        // The browser counts values >= minimum and divides by `years` to get the
        // typical (climatological) number of sailable days in that month.
        $sailableDays = $spotGuides->mapWithKeys(fn ($guide) => [
            $guide->title => $guide->sailableDays
                ->groupBy('month')
                ->map(fn ($monthDays) => [
                    'values' => $monthDays->map(fn ($day) => (float) $day->qualifying_wind_kts)->values()->toArray(),
                    'years' => $monthDays->pluck('year')->unique()->count(),
                ])
                ->toArray(),
        ])->toArray();

        // "Typical year" climate: monthly averages collapsed across all held years,
        // keyed by title (matching the chart legend labels), sorted by month.
        $monthNames = [1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April', 5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August', 9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'];
        $average = fn ($collection, string $column) => round($collection->avg($column), 1);
        $climate = $spotGuides->mapWithKeys(fn ($guide) => [
            $guide->title => $guide->weatherRecords
                ->groupBy('month')
                ->sortKeys()
                ->map(fn ($monthRecords, $monthNumber) => [
                    'month' => $monthNames[(int) $monthNumber] ?? '',
                    'avgTemp' => $average($monthRecords, 'avg_temp'),
                    'ktsWind' => $average($monthRecords, 'kts_wind'),
                    'ktsGust' => $average($monthRecords, 'kts_gust'),
                    'mphWind' => (int) round($monthRecords->avg('mph_wind')),
                    'mphGust' => (int) round($monthRecords->avg('mph_gust')),
                    'kphWind' => (int) round($monthRecords->avg('kph_wind')),
                    'kphGust' => (int) round($monthRecords->avg('kph_gust')),
                ])
                ->values()
                ->toArray(),
        ])->toArray();
```

Update the return array (lines 85–104): remove `'weatherData' => $weatherData,` and add:

```php
            'sailableDays' => $sailableDays,
            'climate' => $climate,
```

Also update the method's PHPDoc (lines 17–23) to describe `sailableDays` and `climate` instead of `weatherData`.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=DestinationSailablePayloadTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/DestinationController.php tests/Feature/DestinationSailablePayloadTest.php database/factories/WeatherRecordFactory.php
git commit -m "feat: destinations controller ships sailableDays + climate props"
```

---

### Task 4: JS ranking helpers (`sailableDays.ts`)

**Files:**
- Create: `resources/js/Helpers/sailableDays.ts`
- Test: `resources/js/Helpers/__tests__/sailableDays.test.ts`

**Interfaces:**
- Produces:
  - `type WindUnit = 'kts' | 'mph' | 'kph'`
  - `interface SailableMonth { values: number[]; years: number }`
  - `type SailableDataset = Record<string, Record<number, SailableMonth>>`
  - `unitToKts(value: number, unit: WindUnit): number`
  - `ktsToUnit(kts: number, unit: WindUnit): number`
  - `MIN_OPTIONS: Record<WindUnit, number[]>`
  - `snapToUnitOption(kts: number, unit: WindUnit): number` — nearest option in that unit
  - `sailableDaysInMonth(month: SailableMonth | undefined, minKts: number): number`
  - `interface RankedSpot { title: string; avgDaysThisMonth: number; daysPerMonth: number[] }` (`daysPerMonth` length 12, index 0 = January)
  - `rankSpots(dataset: SailableDataset, titles: string[], month: number, minKts: number): RankedSpot[]`

- [ ] **Step 1: Write the failing test**

```ts
// resources/js/Helpers/__tests__/sailableDays.test.ts
import { describe, it, expect } from 'vitest'
import {
    unitToKts, ktsToUnit, snapToUnitOption, sailableDaysInMonth, rankSpots,
    type SailableDataset,
} from '@/Helpers/sailableDays'

describe('unit conversion', () => {
    it('round-trips kts through mph', () => {
        expect(ktsToUnit(20, 'kts')).toBe(20)
        expect(unitToKts(ktsToUnit(20, 'mph'), 'mph')).toBeCloseTo(20, 5)
    })
    it('snaps a kts value to the nearest option in the target unit', () => {
        // 20 kts ~= 23.0 mph -> nearest 5-step mph option is 25
        expect(snapToUnitOption(20, 'mph')).toBe(25)
        expect(snapToUnitOption(20, 'kts')).toBe(20)
    })
})

describe('sailableDaysInMonth', () => {
    it('counts values >= minimum and divides by years', () => {
        const month = { values: [22, 12, 25, 8, 30, 21], years: 2 }
        // >= 20kts: 22,25,30,21 => 4 days across 2 years => avg 2
        expect(sailableDaysInMonth(month, 20)).toBe(2)
    })
    it('returns 0 for an undefined month or zero years', () => {
        expect(sailableDaysInMonth(undefined, 20)).toBe(0)
        expect(sailableDaysInMonth({ values: [30], years: 0 }, 20)).toBe(0)
    })
})

describe('rankSpots', () => {
    const dataset: SailableDataset = {
        Windy: { 8: { values: [30, 30, 30, 30], years: 1 } },   // Aug: 4 days
        Calm: { 8: { values: [10, 10], years: 1 } },            // Aug: 0 days
        Mid: { 8: { values: [25, 25], years: 1 }, 7: { values: [25, 25, 25], years: 1 } }, // Aug: 2
    }

    it('ranks by average sailable days in the selected month, descending', () => {
        const ranked = rankSpots(dataset, ['Windy', 'Calm', 'Mid'], 8, 20)
        expect(ranked.map((row) => row.title)).toEqual(['Windy', 'Mid', 'Calm'])
        expect(ranked[0].avgDaysThisMonth).toBe(4)
        expect(ranked[2].avgDaysThisMonth).toBe(0)
    })

    it('breaks ties by peak month then alphabetically', () => {
        const tie: SailableDataset = {
            Bravo: { 8: { values: [25, 25], years: 1 }, 7: { values: [25], years: 1 } }, // Aug 2, peak 2
            Alpha: { 8: { values: [25, 25], years: 1 }, 7: { values: [25, 25, 25, 25], years: 1 } }, // Aug 2, peak 4
        }
        const ranked = rankSpots(tie, ['Bravo', 'Alpha'], 8, 20)
        // Same Aug count (2); Alpha has the higher peak month (4) so it leads.
        expect(ranked.map((row) => row.title)).toEqual(['Alpha', 'Bravo'])
    })

    it('fills daysPerMonth with 12 entries indexed from January', () => {
        const ranked = rankSpots(dataset, ['Mid'], 8, 20)
        expect(ranked[0].daysPerMonth).toHaveLength(12)
        expect(ranked[0].daysPerMonth[7]).toBe(2) // August
        expect(ranked[0].daysPerMonth[6]).toBe(3) // July
        expect(ranked[0].daysPerMonth[0]).toBe(0) // January (no data)
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- sailableDays`
Expected: FAIL — module `@/Helpers/sailableDays` not found.

- [ ] **Step 3: Write the helper**

```ts
// resources/js/Helpers/sailableDays.ts
//
// Client-side sailable-days ranking for the destinations page. A day is
// "sailable" at a minimum X (kts) when its stored 2nd-windiest sailing-window
// hour >= X. Per month we hold every such daily value pooled across the years we
// have; the typical sailable-day count is (values >= X) / years. Spots are then
// ranked by the selected month's typical count.

export type WindUnit = 'kts' | 'mph' | 'kph'

/** One spot-month: every day's qualifying wind (kts), pooled across `years` years. */
export interface SailableMonth {
    values: number[]
    years: number
}

/** title -> month (1-12) -> pooled daily data. */
export type SailableDataset = Record<string, Record<number, SailableMonth>>

/** Multipliers from knots to each display unit. */
const KTS_TO: Record<WindUnit, number> = { kts: 1, mph: 1.15078, kph: 1.852 }

/** Selectable minimum-wind options per unit, in steps of 5 (roughly equivalent ranges). */
export const MIN_OPTIONS: Record<WindUnit, number[]> = {
    kts: [5, 10, 15, 20, 25, 30, 35, 40, 45, 50],
    mph: [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55],
    kph: [10, 20, 30, 40, 50, 60, 70, 80, 90],
}

/** Convert a value in the given unit to knots. */
export const unitToKts = (value: number, unit: WindUnit): number => value / KTS_TO[unit]

/** Convert a knot value to the given unit. */
export const ktsToUnit = (kts: number, unit: WindUnit): number => kts * KTS_TO[unit]

/**
 * Given a wind strength in knots, return the nearest selectable option (in the
 * target unit's own scale). Used when the user switches units so the chosen
 * strength is preserved rather than reset.
 */
export const snapToUnitOption = (kts: number, unit: WindUnit): number => {
    const inUnit = ktsToUnit(kts, unit)
    return MIN_OPTIONS[unit].reduce((nearest, option) =>
        Math.abs(option - inUnit) < Math.abs(nearest - inUnit) ? option : nearest
    )
}

/**
 * Typical number of sailable days in a month for the given minimum (kts):
 * count of pooled daily values at/above the minimum, divided by the year count.
 */
export const sailableDaysInMonth = (month: SailableMonth | undefined, minKts: number): number => {
    if (!month || month.years <= 0) {
        return 0
    }
    const qualifyingDays = month.values.filter((value) => value >= minKts).length
    return qualifyingDays / month.years
}

/** A spot ranked for a selected month: this month's count plus all 12 months. */
export interface RankedSpot {
    title: string
    avgDaysThisMonth: number
    daysPerMonth: number[]
}

/**
 * Rank spots by typical sailable days in `month` (1-12) at `minKts`, descending.
 * Ties break by the spot's single best month, then alphabetically by title, so
 * the order is deterministic and shareable. Spots with no qualifying days remain
 * in the list (at the bottom).
 */
export const rankSpots = (
    dataset: SailableDataset,
    titles: string[],
    month: number,
    minKts: number
): RankedSpot[] => {
    const ranked: RankedSpot[] = titles.map((title) => {
        const spotMonths = dataset[title] ?? {}
        const daysPerMonth = Array.from({ length: 12 }, (_unused, index) =>
            sailableDaysInMonth(spotMonths[index + 1], minKts)
        )
        return {
            title,
            avgDaysThisMonth: daysPerMonth[month - 1],
            daysPerMonth,
        }
    })

    const peak = (row: RankedSpot) => Math.max(...row.daysPerMonth)

    return ranked.sort((first, second) =>
        second.avgDaysThisMonth - first.avgDaysThisMonth ||
        peak(second) - peak(first) ||
        first.title.localeCompare(second.title)
    )
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- sailableDays`
Expected: PASS (all describe blocks).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Helpers/sailableDays.ts resources/js/Helpers/__tests__/sailableDays.test.ts
git commit -m "feat: client-side sailable-days ranking helpers"
```

---

### Task 5: URL filter state helpers (`destinationFilters.ts`)

**Files:**
- Create: `resources/js/Helpers/destinationFilters.ts`
- Test: `resources/js/Helpers/__tests__/destinationFilters.test.ts`

**Interfaces:**
- Produces:
  - `type GroupBy = 'continent' | 'country' | 'global'`
  - `interface DestinationFilters { month: number; min: number; unit: WindUnit; group: GroupBy; spots: string[] }` (`min` is in `unit`; `spots` empty = all)
  - `parseFilters(search: string, defaults: { month: number }): DestinationFilters`
  - `filtersToQuery(filters: DestinationFilters): Record<string, string>`

- [ ] **Step 1: Write the failing test**

```ts
// resources/js/Helpers/__tests__/destinationFilters.test.ts
import { describe, it, expect } from 'vitest'
import { parseFilters, filtersToQuery, type DestinationFilters } from '@/Helpers/destinationFilters'

describe('destination filters URL sync', () => {
    it('falls back to defaults on an empty query', () => {
        const filters = parseFilters('', { month: 7 })
        expect(filters).toEqual({ month: 7, min: 20, unit: 'kts', group: 'continent', spots: [] })
    })

    it('parses a full query string', () => {
        const filters = parseFilters('?month=8&min=25&unit=mph&group=global&spots=vassiliki,tarifa', { month: 7 })
        expect(filters).toEqual({ month: 8, min: 25, unit: 'mph', group: 'global', spots: ['vassiliki', 'tarifa'] })
    })

    it('round-trips filters through query and back', () => {
        const original: DestinationFilters = { month: 3, min: 30, unit: 'kph', group: 'country', spots: ['dahab'] }
        const restored = parseFilters('?' + new URLSearchParams(filtersToQuery(original)).toString(), { month: 7 })
        expect(restored).toEqual(original)
    })

    it('ignores invalid values and clamps to defaults', () => {
        const filters = parseFilters('?month=99&unit=furlongs&group=nonsense', { month: 7 })
        expect(filters.month).toBe(7)
        expect(filters.unit).toBe('kts')
        expect(filters.group).toBe('continent')
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- destinationFilters`
Expected: FAIL — module not found.

- [ ] **Step 3: Write the helper**

```ts
// resources/js/Helpers/destinationFilters.ts
//
// Serialise/parse the destinations-page filter state to and from the URL query
// string, so a filtered view is shareable and bookmarkable. `min` is stored in
// the user's chosen unit; empty `spots` means "all destinations".

import type { WindUnit } from '@/Helpers/sailableDays'

export type GroupBy = 'continent' | 'country' | 'global'

export interface DestinationFilters {
    month: number
    min: number
    unit: WindUnit
    group: GroupBy
    spots: string[]
}

const UNITS: WindUnit[] = ['kts', 'mph', 'kph']
const GROUPS: GroupBy[] = ['continent', 'country', 'global']
const DEFAULT_MIN = 20

/**
 * Build filter state from a URL query string, falling back to defaults for any
 * missing or invalid parameter. `defaults.month` is the current month (1-12),
 * supplied by the caller so this stays a pure function.
 */
export const parseFilters = (search: string, defaults: { month: number }): DestinationFilters => {
    const params = new URLSearchParams(search)

    const monthParam = Number(params.get('month'))
    const month = Number.isInteger(monthParam) && monthParam >= 1 && monthParam <= 12 ? monthParam : defaults.month

    const minParam = Number(params.get('min'))
    const min = Number.isFinite(minParam) && minParam > 0 ? minParam : DEFAULT_MIN

    const unitParam = params.get('unit') as WindUnit | null
    const unit = unitParam && UNITS.includes(unitParam) ? unitParam : 'kts'

    const groupParam = params.get('group') as GroupBy | null
    const group = groupParam && GROUPS.includes(groupParam) ? groupParam : 'continent'

    const spotsParam = params.get('spots')
    const spots = spotsParam ? spotsParam.split(',').filter(Boolean) : []

    return { month, min, unit, group, spots }
}

/** Serialise filter state to a flat query-param map (omitting empty spots). */
export const filtersToQuery = (filters: DestinationFilters): Record<string, string> => {
    const query: Record<string, string> = {
        month: String(filters.month),
        min: String(filters.min),
        unit: filters.unit,
        group: filters.group,
    }
    if (filters.spots.length > 0) {
        query.spots = filters.spots.join(',')
    }
    return query
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- destinationFilters`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Helpers/destinationFilters.ts resources/js/Helpers/__tests__/destinationFilters.test.ts
git commit -m "feat: destinations filter URL serialise/parse helpers"
```

---

### Task 6: Sailable-days chart data helper + `SailableDaysChart`

**Files:**
- Create: `resources/js/Helpers/sailableChartData.ts`
- Create: `resources/js/Components/Destinations/SailableDaysChart.tsx`
- Test: `resources/js/Helpers/__tests__/sailableChartData.test.ts`

**Interfaces:**
- Consumes: `RankedSpot[]` from Task 4.
- Produces:
  - `MONTH_LABELS: string[]` (length 12, `['Jan', ... 'Dec']`)
  - `prepareSailableChartData(ranked: RankedSpot[]): Array<Record<string, number | string>>` — Recharts rows `{ month: 'Jan', [title]: days, ... }`, 12 rows, values rounded to 1 dp.
  - `SailableDaysChart` component — props `{ ranked: RankedSpot[]; colours: Record<string, string>; selectedMonth: number; minLabel: string }`.

- [ ] **Step 1: Write the failing test**

```ts
// resources/js/Helpers/__tests__/sailableChartData.test.ts
import { describe, it, expect } from 'vitest'
import { prepareSailableChartData, MONTH_LABELS } from '@/Helpers/sailableChartData'
import type { RankedSpot } from '@/Helpers/sailableDays'

describe('prepareSailableChartData', () => {
    it('produces one row per month with a key per spot', () => {
        const ranked: RankedSpot[] = [
            { title: 'Windy', avgDaysThisMonth: 4, daysPerMonth: [0, 0, 0, 0, 0, 0, 0, 4, 0, 0, 0, 0] },
            { title: 'Mid', avgDaysThisMonth: 2, daysPerMonth: [0, 0, 0, 0, 0, 0, 3, 2, 0, 0, 0, 0] },
        ]
        const rows = prepareSailableChartData(ranked)
        expect(rows).toHaveLength(12)
        expect(MONTH_LABELS).toHaveLength(12)
        expect(rows[7]).toEqual({ month: 'Aug', Windy: 4, Mid: 2 })
        expect(rows[6]).toEqual({ month: 'Jul', Windy: 0, Mid: 3 })
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- sailableChartData`
Expected: FAIL — module not found.

- [ ] **Step 3: Write the data helper**

```ts
// resources/js/Helpers/sailableChartData.ts
//
// Pivot ranked-spot data into Recharts rows for the "sailable days per month"
// line chart: one row per calendar month, one numeric key per spot title.

import type { RankedSpot } from '@/Helpers/sailableDays'

/** Short month labels, index 0 = January. */
export const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']

/**
 * Build 12 Recharts rows ({ month, [title]: days }) from ranked spots. Day
 * counts are rounded to 1 dp for display.
 */
export const prepareSailableChartData = (
    ranked: RankedSpot[]
): Array<Record<string, number | string>> =>
    MONTH_LABELS.map((label, monthIndex) => {
        const row: Record<string, number | string> = { month: label }
        ranked.forEach((spot) => {
            row[spot.title] = Math.round(spot.daysPerMonth[monthIndex] * 10) / 10
        })
        return row
    })
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- sailableChartData`
Expected: PASS.

- [ ] **Step 5: Write the chart component** (no unit test — presentational; verified in the browser in Task 9)

```tsx
// resources/js/Components/Destinations/SailableDaysChart.tsx
//
// "Sailable days per month" comparison chart: one line per selected spot,
// y-axis = typical sailable days, with the selected month marked by a reference
// line. Reacts to the minimum/unit/spot filters via the ranked data it is given.

import {
    LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, ReferenceLine, Legend,
} from 'recharts'
import { prepareSailableChartData, MONTH_LABELS } from '@/Helpers/sailableChartData'
import type { RankedSpot } from '@/Helpers/sailableDays'

interface Props {
    ranked: RankedSpot[]
    colours: Record<string, string>
    /** 1-12; the month currently driving the ranking, highlighted on the chart. */
    selectedMonth: number
    /** e.g. "20 kts" — shown in the axis label so the chart reads on its own. */
    minLabel: string
}

/**
 * Render the sailable-days-per-month line chart for the ranked spots.
 */
const SailableDaysChart = ({ ranked, colours, selectedMonth, minLabel }: Props) => {
    const data = prepareSailableChartData(ranked)

    return (
        <div className="bg-white p-5 lg:p-6 border border-secondary/10">
            <h3 className="text-secondary font-medium mb-1">Sailable days per month</h3>
            <p className="text-secondary/50 text-sm mb-5">
                Typical days with 2+ hours at or above {minLabel}
            </p>
            <ResponsiveContainer width="100%" height={360}>
                <LineChart data={data} margin={{ top: 8, right: 16, bottom: 4, left: -8 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="rgba(0,0,0,0.08)" />
                    <XAxis dataKey="month" tick={{ fontSize: 12 }} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                    <Tooltip />
                    <Legend />
                    <ReferenceLine
                        x={MONTH_LABELS[selectedMonth - 1]}
                        stroke="hsl(11 61% 58%)"
                        strokeDasharray="4 2"
                    />
                    {ranked.map((spot) => (
                        <Line
                            key={spot.title}
                            type="monotone"
                            dataKey={spot.title}
                            stroke={colours[spot.title]}
                            strokeWidth={2}
                            dot={false}
                        />
                    ))}
                </LineChart>
            </ResponsiveContainer>
        </div>
    )
}

export default SailableDaysChart
```

- [ ] **Step 6: Commit**

```bash
git add resources/js/Helpers/sailableChartData.ts resources/js/Helpers/__tests__/sailableChartData.test.ts resources/js/Components/Destinations/SailableDaysChart.tsx
git commit -m "feat: sailable-days-per-month chart + data helper"
```

---

### Task 7: Adapt wind & temp charts to the typical-year `climate` shape

**Files:**
- Create: `resources/js/Helpers/climate.ts` (types + pivot for 12-month climate data)
- Modify: `resources/js/Components/Destinations/AllDestinationsWindChart.tsx`
- Modify: `resources/js/Components/Destinations/AllDestinationsTempChart.tsx`
- Test: `resources/js/Helpers/__tests__/climate.test.ts`

**Interfaces:**
- Produces:
  - `interface ClimateMonth { month: string; avgTemp: number; ktsWind: number; ktsGust: number; mphWind: number; mphGust: number; kphWind: number; kphGust: number }`
  - `type ClimateDataset = Record<string, ClimateMonth[]>`
  - `prepareClimateData(dataset: ClimateDataset, datapoint: keyof ClimateMonth): Array<Record<string, any>>` — 12-ish rows `{ month, [title]: value }`.
- Wind chart new props: `{ climate: ClimateDataset; activeDestinations: SelectOption[]; showAverageGustData; activeWindUnit; setActiveWindUnit; setShowAverageGustData; colours; selectedMonth: number }` — the `activeYear`/`weatherData` props are gone; unit control stays inside the chart (moves to the page bar in Task 8's Index rewrite? — **No:** keep unit control here, but it is now driven by shared page state passed as props, same as today).

> Rationale: today the wind chart already owns the unit + gust controls via props from the page. We keep that contract; only the *data source* changes from `weatherData[year]` to `climate` (already a single typical-year series, so no year lookup).

- [ ] **Step 1: Write the failing test**

```ts
// resources/js/Helpers/__tests__/climate.test.ts
import { describe, it, expect } from 'vitest'
import { prepareClimateData, type ClimateDataset } from '@/Helpers/climate'

describe('prepareClimateData', () => {
    const dataset: ClimateDataset = {
        Tarifa: [
            { month: 'July', avgTemp: 26, ktsWind: 18, ktsGust: 22, mphWind: 21, mphGust: 25, kphWind: 33, kphGust: 41 },
            { month: 'August', avgTemp: 28, ktsWind: 21, ktsGust: 26, mphWind: 24, mphGust: 30, kphWind: 39, kphGust: 48 },
        ],
        Dahab: [
            { month: 'August', avgTemp: 33, ktsWind: 15, ktsGust: 19, mphWind: 17, mphGust: 22, kphWind: 28, kphGust: 35 },
        ],
    }

    it('pivots a datapoint to month rows keyed by title', () => {
        const rows = prepareClimateData(dataset, 'ktsWind')
        const august = rows.find((row) => row.month === 'August')
        expect(august).toEqual({ month: 'August', Tarifa: 21, Dahab: 15 })
        const july = rows.find((row) => row.month === 'July')
        expect(july).toEqual({ month: 'July', Tarifa: 18 })
    })
})
```

- [ ] **Step 2: Run test to verify it fails**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- climate`
Expected: FAIL — module not found.

- [ ] **Step 3: Write `climate.ts`**

```ts
// resources/js/Helpers/climate.ts
//
// "Typical year" climate data (monthly averages collapsed across all held years)
// for the destinations wind/temperature charts, plus a pivot into Recharts rows.

/** One typical-year month for a spot (averages across the years we hold). */
export interface ClimateMonth {
    month: string
    avgTemp: number
    ktsWind: number
    ktsGust: number
    mphWind: number
    mphGust: number
    kphWind: number
    kphGust: number
}

/** title -> 12-ish month entries (only months we have data for), month-ordered. */
export type ClimateDataset = Record<string, ClimateMonth[]>

/**
 * Pivot a climate dataset into Recharts rows for one datapoint (e.g. 'ktsWind'):
 * one row per month present in any spot, each carrying a key per spot title.
 * Months are emitted in first-seen order (the server sorts by month).
 */
export const prepareClimateData = (
    dataset: ClimateDataset,
    datapoint: keyof ClimateMonth
): Array<Record<string, any>> => {
    const monthOrder: string[] = []
    Object.values(dataset).forEach((months) => {
        months.forEach((entry) => {
            if (!monthOrder.includes(entry.month)) {
                monthOrder.push(entry.month)
            }
        })
    })

    return monthOrder.map((monthName) => {
        const row: Record<string, any> = { month: monthName }
        Object.entries(dataset).forEach(([title, months]) => {
            const monthData = months.find((entry) => entry.month === monthName)
            if (monthData) {
                row[title] = monthData[datapoint]
            }
        })
        return row
    })
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js -- climate`
Expected: PASS.

- [ ] **Step 5: Rewrite the wind chart to consume `climate`**

Open `resources/js/Components/Destinations/AllDestinationsWindChart.tsx`. Replace its data wiring:
- Change the props type: remove `weatherData` + `activeYear`; add `climate: ClimateDataset` and `selectedMonth: number`.
- Replace the `prepareYearlyWindData(weatherData, activeYear, datapoint)` call with `prepareClimateData(filteredClimate, datapoint as keyof ClimateMonth)`, where `filteredClimate` is `climate` narrowed to the active destination titles (mirror the existing active-destination filtering the chart already does on the series list).
- The `datapoint` composition (`` `${activeWindUnit}${showAverageGustData ? 'Gust' : 'Wind'}` ``) is unchanged.
- Add a `<ReferenceLine x={selectedMonth-1's month name}>` marker consistent with `SailableDaysChart` (import full month names or map index→name). Use the month name from `prepareClimateData` rows; if the selected month has no data, skip the reference line.
- Update imports: `import { prepareClimateData, type ClimateDataset, type ClimateMonth } from '@/Helpers/climate'` and drop the `prepareYearlyWindData`/`WeatherDataset` imports.
- Keep the unit radios + gust toggle exactly as they are (still driven by the props from the page).

- [ ] **Step 6: Rewrite the temp chart to consume `climate`**

Open `resources/js/Components/Destinations/AllDestinationsTempChart.tsx`. Same shape of change: props lose `weatherData`+`activeYear`, gain `climate: ClimateDataset` and `selectedMonth`; replace `prepareYearlyTempData(weatherData, activeYear)` with `prepareClimateData(filteredClimate, 'avgTemp')`; add the selected-month reference line.

- [ ] **Step 7: Verify the build compiles**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npx vite build --logLevel error`
Expected: no errors. (Index.tsx still references the old props at this point — if the build fails only on `Destinations/Index.tsx`, that is expected and fixed in Task 8; the chart files themselves must compile.)

- [ ] **Step 8: Commit**

```bash
git add resources/js/Helpers/climate.ts resources/js/Helpers/__tests__/climate.test.ts resources/js/Components/Destinations/AllDestinationsWindChart.tsx resources/js/Components/Destinations/AllDestinationsTempChart.tsx
git commit -m "feat: wind/temp charts consume typical-year climate data"
```

---

### Task 8: New filter bar + rework `Destinations/Index.tsx`

**Files:**
- Create: `resources/js/Components/Destinations/DestinationFilterBar.tsx`
- Modify: `resources/js/Components/Common/DestinationCard.tsx` (add optional `stat` + `continentLabel`)
- Modify: `resources/js/Pages/Destinations/Index.tsx` (new props, filter state, ranked layouts, charts)
- Delete: `resources/js/Components/Destinations/FilterDataset.tsx` is superseded — leave the file in place only if still imported elsewhere; otherwise delete it. (Grep first: `grep -rl FilterDataset resources/js`.)

**Interfaces:**
- Consumes: `sailableDays` + `climate` props (Tasks 3, 4, 7), `rankSpots`, `parseFilters`/`filtersToQuery`, `SailableDaysChart`, adapted wind/temp charts.
- Produces: `DestinationFilterBar` with props `{ monthOptions; groupOptions; destinationOptions; filters: DestinationFilters; onChange(next: DestinationFilters): void }`.

- [ ] **Step 1: Add `stat` + `continentLabel` to `DestinationCard`**

Edit `DestinationCard.tsx`: extend the props interface and render. Add to `DestinationCardProps`:

```tsx
    /** e.g. "≈ 19 days ≥ 20 kts" — the sailable-days figure for the active filter; null hides it. */
    stat?: string | null
    /** Continent label shown in the flat "global" ranking so region is still visible; null hides it. */
    continentLabel?: string | null
```

Add `stat` and `continentLabel` to the destructured params, then render `stat` as a prominent pill above the title and `continentLabel` next to the country. Insert inside the bottom text block (after the `<h3>`):

```tsx
            {stat && (
                <p className="text-primary-lighter text-[11px] mt-1 font-medium tracking-wide tabular-nums">
                    {stat}
                </p>
            )}
```

And extend the country line:

```tsx
            {(countryName || continentLabel) && (
                <p className="text-white/55 text-[10px] mt-1.5 uppercase tracking-[0.2em]">
                    {[countryName, continentLabel].filter(Boolean).join(' · ')}
                </p>
            )}
```

(Remove the original `countryName`-only paragraph.)

- [ ] **Step 2: Write `DestinationFilterBar`**

```tsx
// resources/js/Components/Destinations/DestinationFilterBar.tsx
//
// Top-of-page search-style filter bar for the destinations index. Drives the
// card ranking/layout AND the charts below, and is mirrored to the URL. All
// state lives in the parent; this component is presentational (props in,
// onChange out).

import Select from 'react-select'
import Icon from '@/Components/Common/Icon'
import { faSlidersH } from '@fortawesome/free-solid-svg-icons'
import { MIN_OPTIONS, snapToUnitOption, unitToKts, type WindUnit } from '@/Helpers/sailableDays'
import type { DestinationFilters, GroupBy } from '@/Helpers/destinationFilters'
import type { SelectOption } from '@/Helpers/selectTypes'

// react-select styling reused from the old FilterDataset (brand teal, square corners).
const selectStyles = { /* COPY the `selectStyles` object verbatim from the deleted FilterDataset.tsx */ } as any

interface Props {
    monthOptions: { label: string; value: number }[]
    groupOptions: { label: string; value: GroupBy }[]
    destinationOptions: SelectOption[]
    filters: DestinationFilters
    onChange: (next: DestinationFilters) => void
}

/**
 * Render the destinations filter bar (Month / Group by / Spots / Unit / Minimum).
 */
const DestinationFilterBar = ({ monthOptions, groupOptions, destinationOptions, filters, onChange }: Props) => {
    const unitOptions: { label: string; value: WindUnit }[] = [
        { label: 'kts', value: 'kts' }, { label: 'mph', value: 'mph' }, { label: 'kph', value: 'kph' },
    ]
    const minOptions = MIN_OPTIONS[filters.unit].map((value) => ({ label: `${value} ${filters.unit}`, value }))

    const isAllSpots = filters.spots.length === 0
    const selectedSpotOptions = destinationOptions.filter((opt) => filters.spots.includes(opt.value))

    /** When the unit changes, preserve the wind strength by snapping the current minimum into the new unit. */
    const handleUnitChange = (unit: WindUnit) => {
        const currentKts = unitToKts(filters.min, filters.unit)
        onChange({ ...filters, unit, min: snapToUnitOption(currentKts, unit) })
    }

    return (
        <div className="bg-white border-y border-secondary/10 sticky top-0 z-20">
            <div className="container mx-auto py-4 lg:py-5">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:gap-5">
                    <div className="flex items-center gap-2.5 shrink-0">
                        <Icon icon={faSlidersH} customClasses="text-primary" size="size-4" />
                        <span className="text-primary text-xs uppercase tracking-[0.2em] font-medium">Find your spot</span>
                    </div>

                    <div className="lg:w-36 shrink-0">
                        <Select
                            options={monthOptions}
                            value={monthOptions.find((opt) => opt.value === filters.month)}
                            onChange={(opt: any) => opt && onChange({ ...filters, month: Number(opt.value) })}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>

                    <div className="lg:w-40 shrink-0">
                        <Select
                            options={groupOptions}
                            value={groupOptions.find((opt) => opt.value === filters.group)}
                            onChange={(opt: any) => opt && onChange({ ...filters, group: opt.value as GroupBy })}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>

                    <div className="flex-1 min-w-0">
                        <Select
                            isMulti
                            options={destinationOptions}
                            value={isAllSpots ? null : selectedSpotOptions}
                            placeholder="All destinations"
                            onChange={(opts: any) => onChange({ ...filters, spots: (opts ?? []).map((opt: SelectOption) => opt.value) })}
                            styles={selectStyles}
                        />
                    </div>

                    <div className="lg:w-28 shrink-0">
                        <Select
                            options={unitOptions}
                            value={unitOptions.find((opt) => opt.value === filters.unit)}
                            onChange={(opt: any) => opt && handleUnitChange(opt.value as WindUnit)}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>

                    <div className="lg:w-36 shrink-0">
                        <Select
                            options={minOptions}
                            value={minOptions.find((opt) => opt.value === filters.min) ?? minOptions[0]}
                            onChange={(opt: any) => opt && onChange({ ...filters, min: Number(opt.value) })}
                            styles={selectStyles}
                            isSearchable={false}
                        />
                    </div>
                </div>
            </div>
        </div>
    )
}

export default DestinationFilterBar
```

> Sub-step: create `resources/js/Helpers/selectTypes.ts` exporting `export interface SelectOption { label: string; value: string }` (the old `SelectOption` lived in `FilterDataset.tsx`; give it a neutral home so both the bar and Index can import it). Copy the `selectStyles` object from the old `FilterDataset.tsx` verbatim into `DestinationFilterBar.tsx` where marked.

- [ ] **Step 3: Rewrite `Destinations/Index.tsx`**

Replace the component wholesale. Key changes: new props (`sailableDays`, `climate` instead of `weatherData`); filter state initialised from the URL via `parseFilters`; every filter change calls `updateFilters`, which writes state and pushes the query string with Inertia's `router.get(..., { preserveState: true, preserveScroll: true, replace: true })`; ranked layouts driven by `rankSpots`; charts consume `climate` + ranked data.

```tsx
import { useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import { groupBy } from 'lodash'

import Layout from '@/Layouts/Layout'
import DestinationCard from '@/Components/Common/DestinationCard'
import FeaturedHero from '@/Components/Common/FeaturedHero'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import DestinationsMap from '@/Components/Map/DestinationsMap'
import DestinationFilterBar from '@/Components/Destinations/DestinationFilterBar'
import SailableDaysChart from '@/Components/Destinations/SailableDaysChart'
import AllDestinationsWindChart from '@/Components/Destinations/AllDestinationsWindChart'
import AllDestinationsTempChart from '@/Components/Destinations/AllDestinationsTempChart'
import AnimateInView from '@/Components/Common/AnimateInView'
import { getSpotGuideColours } from '@/Helpers/colours'
import { rankSpots, ktsToUnit, unitToKts, type SailableDataset } from '@/Helpers/sailableDays'
import { parseFilters, filtersToQuery, type DestinationFilters, type GroupBy } from '@/Helpers/destinationFilters'
import type { ClimateDataset } from '@/Helpers/climate'
import type { SelectOption } from '@/Helpers/selectTypes'
import type { FocalImage } from '@/types/media'

interface Author { kind: 'house' | 'contributor'; name: string | null; slug: string | null }

interface SpotGuide {
    id: number
    title: string
    slug: string
    latitude: number | null
    longitude: number | null
    country: { name: string; slug: string; continent: string } | null
    thumbnail: FocalImage | null
    author: Author
}

interface Props {
    spotGuides: SpotGuide[]
    sailableDays: SailableDataset
    climate: ClimateDataset
    showProvenance: boolean
    static_masthead: FocalImage | null
    featuredSpotGuide: { id: number; title: string; slug: string; country: string | null; thumbnail: FocalImage | null } | null
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const CONTINENT_LABELS: Record<string, string> = {
    africa: 'Africa', asia: 'Asia', europe: 'Europe',
    'north-america': 'North America', 'south-america': 'South America', oceania: 'Oceania',
}
const CONTINENT_ORDER = ['europe', 'africa', 'asia', 'north-america', 'south-america', 'oceania']
const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

/**
 * Destinations index: rank spots by typical sailable days for the chosen month +
 * minimum wind, grouped by continent/country/global, with URL-synced filters and
 * the sailable-days / wind / temperature charts below.
 */
const Index = ({ spotGuides, sailableDays, climate, showProvenance, static_masthead, featuredSpotGuide, meta }: Props) => {
    const titles = Object.keys(climate).sort()
    const colours = useMemo(() => getSpotGuideColours(titles), [titles])
    const destinationOptions: SelectOption[] = spotGuides.map((guide) => ({ label: guide.title, value: guide.title }))

    const currentMonth = new Date().getMonth() + 1
    const initialFilters = parseFilters(typeof window !== 'undefined' ? window.location.search : '', { month: currentMonth })
    const [filters, setFilters] = useState<DestinationFilters>(initialFilters)

    /** Apply a filter change: update local state and mirror to the URL (no server round-trip needed for data). */
    const updateFilters = (next: DestinationFilters) => {
        setFilters(next)
        router.get('/destinations', filtersToQuery(next), { preserveState: true, preserveScroll: true, replace: true })
    }

    const monthOptions = MONTH_NAMES.map((name, index) => ({ label: name, value: index + 1 }))
    const groupOptions: { label: string; value: GroupBy }[] = [
        { label: 'By continent', value: 'continent' },
        { label: 'By country', value: 'country' },
        { label: 'Global ranking', value: 'global' },
    ]

    const minKts = unitToKts(filters.min, filters.unit)
    const activeTitles = filters.spots.length > 0 ? filters.spots : titles
    const ranked = useMemo(
        () => rankSpots(sailableDays, activeTitles, filters.month, minKts),
        [sailableDays, activeTitles, filters.month, minKts]
    )

    // Rank order as a lookup so card grids can sort by it.
    const rankIndex = useMemo(() => {
        const lookup: Record<string, number> = {}
        ranked.forEach((row, index) => { lookup[row.title] = index })
        return lookup
    }, [ranked])

    const spotByTitle = useMemo(() => {
        const lookup: Record<string, SpotGuide> = {}
        spotGuides.forEach((guide) => { lookup[guide.title] = guide })
        return lookup
    }, [spotGuides])

    /** "≈ N days ≥ X unit" stat for a card, from the ranked row. */
    const statFor = (title: string): string => {
        const row = ranked.find((entry) => entry.title === title)
        const days = row ? Math.round(row.avgDaysThisMonth) : 0
        return `≈ ${days} ${days === 1 ? 'day' : 'days'} ≥ ${filters.min} ${filters.unit}`
    }

    const rankedGuides = ranked.map((row) => spotByTitle[row.title]).filter(Boolean) as SpotGuide[]

    const mastheadImage = static_masthead ?? spotGuides.find((s) => s.thumbnail)?.thumbnail ?? null
    const minLabel = `${filters.min} ${filters.unit}`

    /** Build the grouped sections for continent/country grouping, each rank-sorted, groups ordered by their best spot. */
    const buildGroups = (key: 'continent' | 'country') => {
        const grouped = groupBy(
            rankedGuides.filter((guide) => guide.country),
            (guide) => key === 'continent' ? guide.country!.continent : guide.country!.slug
        )
        const entries = Object.entries(grouped)
        // Order groups by their best-ranked member (lowest rankIndex) — the region
        // holding your #1 spot leads. CONTINENT_ORDER is used only for labels now.
        entries.sort(([, first], [, second]) =>
            Math.min(...first.map((guide) => rankIndex[guide.title])) -
            Math.min(...second.map((guide) => rankIndex[guide.title]))
        )
        return entries
    }

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            <StaticMasthead imageUrl={mastheadImage} title="Destinations" eyebrow="Windsurfing around the world" />

            {/* Filter bar — drives ranking + charts, synced to the URL */}
            <DestinationFilterBar
                monthOptions={monthOptions}
                groupOptions={groupOptions}
                destinationOptions={destinationOptions}
                filters={filters}
                onChange={updateFilters}
            />

            {/* Intro */}
            <section className="bg-cream">
                <div className="container mx-auto py-12 lg:py-16">
                    <span className="block w-8 h-0.5 bg-orange mb-5" />
                    <h2 className="font-display text-secondary leading-none tracking-wide" style={{ fontSize: 'clamp(2.2rem, 5vw, 4rem)' }}>
                        Where's Windy in {MONTH_NAMES[filters.month - 1]}?
                    </h2>
                    <p className="text-gray-500 text-base lg:text-lg leading-relaxed mt-4 max-w-2xl">
                        Ranked by the typical number of days each spot blows {filters.min} {filters.unit} or more
                        for at least two hours — set your minimum above.
                    </p>
                </div>
            </section>

            {featuredSpotGuide && (
                <section className="bg-white">
                    <div className="container mx-auto py-14 lg:py-16">
                        <FeaturedHero image={featuredSpotGuide.thumbnail} eyebrow="Featured Destination" title={featuredSpotGuide.title} metaLabel={featuredSpotGuide.country} href={`/destinations/${featuredSpotGuide.slug}`} ctaLabel="Explore guide" />
                    </div>
                </section>
            )}

            <DestinationsMap spotGuides={spotGuides} />

            {/* Card layouts */}
            {filters.group === 'global' ? (
                <section className="bg-white">
                    <div className="container mx-auto pt-14 lg:pt-18">
                        <SectionHeading label={`Best for ${MONTH_NAMES[filters.month - 1]}`} count={rankedGuides.length} />
                    </div>
                    <CardGrid guides={rankedGuides} showProvenance={showProvenance} statFor={statFor} withContinent />
                    <div className="container mx-auto pb-6" />
                </section>
            ) : (
                buildGroups(filters.group).map(([groupKey, guides], sectionIndex) => (
                    <section key={groupKey} className={sectionIndex % 2 === 0 ? 'bg-white' : 'bg-cream'}>
                        <div className="container mx-auto pt-14 lg:pt-18">
                            <SectionHeading
                                label={filters.group === 'continent' ? (CONTINENT_LABELS[groupKey] || groupKey) : (guides[0]?.country?.name || groupKey)}
                                count={guides.length}
                            />
                        </div>
                        <CardGrid guides={guides} showProvenance={showProvenance} statFor={statFor} />
                        <div className="container mx-auto pb-6" />
                    </section>
                ))
            )}

            {/* Charts */}
            {titles.length > 0 && (
                <section className="bg-primary-lightest">
                    <div className="container mx-auto pt-16 lg:pt-20 pb-10 lg:pb-12">
                        <div className="flex items-start gap-4">
                            <div className="mt-2 w-1 h-12 bg-orange rounded-full shrink-0" />
                            <div>
                                <h2 className="font-display text-secondary leading-none tracking-wide" style={{ fontSize: 'clamp(2.5rem, 5vw, 4.5rem)' }}>Wind & Weather Data</h2>
                                <p className="text-secondary/50 text-sm mt-2">Typical-year averages across all destinations · {MONTH_NAMES[filters.month - 1]} highlighted</p>
                            </div>
                        </div>
                    </div>
                    <div className="container mx-auto py-4 lg:py-8 space-y-8">
                        <SailableDaysChart ranked={ranked} colours={colours} selectedMonth={filters.month} minLabel={minLabel} />
                        <AllDestinationsWindChart
                            climate={climate}
                            activeDestinations={activeTitles.map((title) => ({ label: title, value: title }))}
                            showAverageGustData={false}
                            activeWindUnit={filters.unit}
                            setActiveWindUnit={(unit: string) => updateFilters({ ...filters, unit: unit as DestinationFilters['unit'] })}
                            setShowAverageGustData={() => { /* gust toggle handled inside the chart's local state */ }}
                            colours={colours}
                            selectedMonth={filters.month}
                        />
                        <AllDestinationsTempChart climate={climate} activeDestinations={activeTitles.map((title) => ({ label: title, value: title }))} colours={colours} selectedMonth={filters.month} />
                    </div>
                </section>
            )}
        </Layout>
    )
}

/** Continent/country/global section heading. */
const SectionHeading = ({ label, count }: { label: string; count: number }) => (
    <div className="flex items-center gap-5 mb-10 lg:mb-12">
        <div className="flex items-start gap-4">
            <div className="mt-2 w-1 h-10 bg-orange rounded-full shrink-0" />
            <h2 className="font-display text-secondary leading-none tracking-wide" style={{ fontSize: 'clamp(2.2rem, 5vw, 4.5rem)' }}>{label}</h2>
        </div>
        <div className="flex-1 h-px bg-gradient-to-r from-secondary/15 to-transparent hidden md:block" />
        <span className="text-secondary/30 text-sm font-medium tabular-nums hidden md:block">{count} {count === 1 ? 'spot' : 'spots'}</span>
    </div>
)

/** Rank-ordered card grid. */
const CardGrid = ({ guides, showProvenance, statFor, withContinent = false }: {
    guides: SpotGuide[]; showProvenance: boolean; statFor: (title: string) => string; withContinent?: boolean
}) => (
    <AnimateInView tag="ul" animateChildren classes="container mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
        {guides.map((guide) => (
            <li key={guide.id} className="aspect-square">
                <DestinationCard
                    title={guide.title}
                    slug={guide.slug}
                    thumbnail={guide.thumbnail}
                    countryName={guide.country?.name}
                    continentLabel={withContinent ? (CONTINENT_LABELS[guide.country?.continent ?? ''] ?? null) : null}
                    stat={statFor(guide.title)}
                    byline={showProvenance ? (guide.author.kind === 'contributor' && guide.author.name ? `By ${guide.author.name}` : 'Seabound Souls') : null}
                />
            </li>
        ))}
    </AnimateInView>
)

export default Index
```

> Note on the wind chart's gust toggle: it currently expects `showAverageGustData`/`setShowAverageGustData` from the page. To avoid adding gust to the URL scope (out of scope), move that toggle to **local state inside `AllDestinationsWindChart`** as a small follow-up within this step (a `useState(false)` in the chart, dropping the two props). Update the chart's props type accordingly and remove the two props from the Index call above.

- [ ] **Step 4: Verify the build compiles**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npx vite build --logLevel error`
Expected: no errors.

- [ ] **Step 5: Run the JS test suite**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js`
Expected: PASS (all helper suites).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/Destinations/DestinationFilterBar.tsx resources/js/Helpers/selectTypes.ts resources/js/Components/Common/DestinationCard.tsx resources/js/Pages/Destinations/Index.tsx resources/js/Components/Destinations/AllDestinationsWindChart.tsx
git rm resources/js/Components/Destinations/FilterDataset.tsx  # only if grep showed no other importers
git commit -m "feat: URL-synced sailable-days filter bar + ranked destinations layouts"
```

---

### Task 9: Browser verification & full suite

**Files:** none (verification only).

- [ ] **Step 1: Backfill the daily layer locally**

Run (Postgres dev DB, Node not needed):
```bash
php artisan weather:fetch
```
Expected: completes; `spot_sailable_days` now has rows (`php artisan tinker --execute="echo App\Models\SailableDay::count();"` > 0). If no spots have coordinates locally, run `php artisan db:pull-from-production` first (per CLAUDE.md) then re-run.

- [ ] **Step 2: Start dev servers and open the page**

Start Vite (`export PATH=... && npm run dev`) with Herd serving the app, then open `http://seaboundsouls.test/destinations` in the preview browser.

- [ ] **Step 3: Verify the ranking behaviour**

- Change the **Minimum** dropdown → cards reorder instantly, the "≈ N days" stat updates, the sailable-days chart lines move.
- Change **Month** → ranking + reference line move; intro heading month updates.
- Switch **Unit** → the minimum snaps to the nearest option (e.g. 20 kts → 25 mph), ranking unchanged in spirit.
- Switch **Group by** between Continent / Country / Global → layout regroups; Global shows a continent tag on each card.
- Confirm the **URL query string** updates on every change, and that pasting the URL into a fresh tab reproduces the same view.
- Empty the Spots select → label reads "All destinations".

- [ ] **Step 4: Verify both themes and all breakpoints**

Use the preview's dark-mode + resize (mobile 375 / tablet 768 / desktop 1280). Confirm the filter bar wraps sensibly on mobile and no raw colours fail to flip. Screenshot desktop + mobile for the PR.

- [ ] **Step 5: Run the full test suites**

```bash
php artisan test
export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run test:js
```
Expected: both green.

- [ ] **Step 6: Smoke-test the aggregate queries on Postgres**

Confirm `/destinations` renders without error against the Postgres dev DB (the `climate` `AVG`-grouped-by-month and `sailableDays` pooling run there, not on the SQLite test DB). Check `storage/logs/laravel.log` is clean.

- [ ] **Step 7: Commit any fixes, then reconcile + PR**

Run `reconcile-everything` on this branch (folds the history doc into the PR), then open the PR per the project git workflow.

---

## Self-Review notes (author)

- **Spec coverage:** data layer (Task 1–2), sailable-day rule via 2nd-highest hour (Task 2), month-based cross-year ranking (Tasks 3–4), Approach-1 client compute (Tasks 4–8), URL sync (Task 5, wired Task 8), filter bar + defaults incl. "All destinations" label (Task 8), three group-by layouts (Task 8), new days-per-month chart + kept typical-year wind/temp charts + unit control (Tasks 6–8), zero-day spots visible (rankSpots keeps them), TDD + Postgres smoke-test + dark/responsive (Task 9). All spec sections map to a task.
- **Known judgement calls handed to the implementer:** (a) the gust toggle moves to local chart state to keep it out of the URL scope; (b) `selectStyles` is copied into the new bar rather than shared, matching the existing one-off pattern; (c) continent grouping now orders by best-ranked spot (spec-approved) rather than the old editorial `CONTINENT_ORDER`.
