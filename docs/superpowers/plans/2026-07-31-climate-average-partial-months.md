# Climate Averages — Exclude Partial Months Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the `/destinations` wind and temperature charts accurate by ensuring `weather_records` only ever holds **complete** calendar months, so the cross-year average that produces the "typical year" curve is valid.

**Architecture:** The bug is entirely in the write path (`WeatherFetcher`), not the read path. `DestinationController` collapses year-rows into a typical month with a plain `->avg()`, which is correct *provided every row represents a full month*. Today it doesn't: the fetch window starts mid-month, so the oldest month is a stub (as few as 4 days), and nothing prunes, so those stubs freeze permanently and keep voting. Fix: start the window on a month boundary, write a climate row only for months where every calendar day was actually received, and replace a spot's rows per fetch rather than merging — which makes the fetcher self-healing and removes the need for a data migration. **No controller or frontend change.**

**Tech Stack:** Laravel 12, PHPUnit 11, Carbon, Open-Meteo archive API (faked in tests via `Http::fake`).

## Global Constraints

- Tests run on in-memory SQLite; local dev and prod are PostgreSQL. Nothing here uses JSON operators, so no divergence risk.
- TDD is mandatory: write the failing test, watch it fail, then implement.
- `php artisan test` must pass in full before any task is considered done.
- PHPDoc on non-obvious methods; comment the *why*, not the *what*.
- No direct commits to `main` — this work goes on a feature branch, and `reconcile-everything` folds into its PR before merge.
- The daily `spot_sailable_days` layer is **deliberately left alone**. It is coverage-normalised (`qualifying ÷ held × daysInMonth`), so partial months are correct and useful there. Do not add pruning or completeness filtering to it.

---

### Task 1: Start the fetch window on a month boundary and write only complete months

**Files:**
- Modify: `app/Services/WeatherFetcher.php:33` (window start), `app/Services/WeatherFetcher.php:125-141` (monthly write)
- Test: `tests/Feature/WeatherFetcherClimateMonthsTest.php` (create)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `WeatherFetcher::fetchForSpot(SpotGuide $spot): void` — unchanged signature. After this task it writes a `weather_records` row only for months where the count of received days equals the month's calendar length. `$yearMonthMap` entries gain a `days` key that Task 2 also relies on.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/WeatherFetcherClimateMonthsTest.php`:

```php
<?php
// tests/Feature/WeatherFetcherClimateMonthsTest.php
//
// The /destinations charts average weather_records across years with equal
// weight per row, so a row must always represent a COMPLETE calendar month.
// These tests pin that contract at the write path.

namespace Tests\Feature;

use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class WeatherFetcherClimateMonthsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a faked Open-Meteo response whose first chunk carries the given
     * hourly rows and whose remaining chunks are empty. fetchForSpot buckets
     * by each reading's own `time` value, not by which chunk returned it, so
     * putting every reading in the first chunk is safe and keeps tests short.
     *
     * @param  array<int, array{0: string, 1: float|null, 2: float|null, 3: float|null}>  $readings
     */
    private function fakeArchive(array $readings): void
    {
        Http::fake([
            'archive-api.open-meteo.com/*' => Http::sequence()
                ->push(['hourly' => [
                    'time' => array_column($readings, 0),
                    'temperature_2m' => array_column($readings, 1),
                    'wind_speed_10m' => array_column($readings, 2),
                    'wind_gusts_10m' => array_column($readings, 3),
                ]])
                ->whenEmpty(Http::response(['hourly' => [
                    'time' => [], 'temperature_2m' => [], 'wind_speed_10m' => [], 'wind_gusts_10m' => [],
                ]])),
        ]);
    }

    /**
     * Two in-window hourly readings for EVERY day of the given month. A month
     * only becomes a climate row once every one of its days is present, so a
     * "complete month" fixture has to actually be complete.
     *
     * @return array<int, array{0: string, 1: float|null, 2: float, 3: float}>
     */
    private function fullMonthReadings(\Illuminate\Support\Carbon $month, float $temp, float $wind, float $gust): array
    {
        $readings = [];
        $day = $month->copy()->startOfMonth();

        for ($dayNumber = 1; $dayNumber <= $month->daysInMonth; $dayNumber++) {
            $readings[] = [$day->format('Y-m-d').'T09:00', $temp, $wind, $gust];
            $readings[] = [$day->format('Y-m-d').'T10:00', $temp, $wind, $gust];
            $day->addDay();
        }

        return $readings;
    }

    public function test_it_skips_the_incomplete_current_month(): void
    {
        Sleep::fake();

        // A complete past month (six months back is always inside the 3-year
        // window and is never the current month), plus the current month —
        // which is partial by definition and must not become a climate row.
        $completeMonth = now()->subMonths(6)->startOfMonth();
        $currentMonth = now()->startOfMonth();

        $this->fakeArchive(array_merge(
            $this->fullMonthReadings($completeMonth, 20.0, 10.0, 15.0),
            // Only the 1st of the current month — deliberately partial.
            [
                [$currentMonth->format('Y-m-d').'T09:00', 25.0, 30.0, 40.0],
                [$currentMonth->format('Y-m-d').'T10:00', 26.0, 32.0, 42.0],
            ],
        ));

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $this->assertDatabaseHas('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) $completeMonth->year,
            'month' => (int) $completeMonth->month,
        ]);

        $this->assertDatabaseMissing('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) $currentMonth->year,
            'month' => (int) $currentMonth->month,
        ]);
    }

    public function test_the_daily_layer_still_keeps_the_incomplete_current_month(): void
    {
        Sleep::fake();

        // The sailable-days ranking is coverage-normalised, so partial months
        // are correct there. Excluding them from the climate table must not
        // leak into the daily table.
        $currentMonthDay = now()->startOfMonth()->format('Y-m-d');

        $this->fakeArchive([
            [$currentMonthDay.'T09:00', 25.0, 30.0, 40.0],
            [$currentMonthDay.'T10:00', 26.0, 32.0, 42.0],
        ]);

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $this->assertDatabaseHas('spot_sailable_days', [
            'spot_guide_id' => $spot->id,
            'date' => $currentMonthDay,
        ]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=WeatherFetcherClimateMonthsTest`

Expected: `test_it_skips_the_incomplete_current_month` FAILS on the `assertDatabaseMissing` — the current month's row is currently written. `test_the_daily_layer_still_keeps_the_incomplete_current_month` should already PASS (it pins behaviour we must not break).

- [ ] **Step 3: Move the window start to a month boundary**

In `app/Services/WeatherFetcher.php`, replace line 33:

```php
        $startOfRange = now()->subYears(3);
```

with:

```php
        // Start on a month boundary so the OLDEST month in range is complete.
        // A mid-month start wrote a stub row (e.g. 4 days of July 2023) which
        // the /destinations charts then weighted equally with a full 31-day
        // month, because the cross-year average in DestinationController is a
        // plain mean over year-rows. Snapping back to the 1st gains data
        // rather than discarding it.
        $startOfRange = now()->subYears(3)->startOfMonth();
```

- [ ] **Step 4: Count the days behind each month**

The completeness test is "did we actually receive every day of this month", not "does the date range imply we should have". Those differ: Open-Meteo's archive runs roughly five days behind real time, so a month fetched on the 1st of the following month can be inside the window yet still missing its final days. Counting is the honest check and needs no knowledge of the API's lag.

In `app/Services/WeatherFetcher.php`, in the daily → year-month rollup, add a day counter to the bucket initialiser. Replace:

```php
                $yearMonthMap[$key] = ['year' => (int) $year, 'month' => (int) $monthNumber, 'temps' => [], 'winds' => [], 'gusts' => []];
```

with:

```php
                $yearMonthMap[$key] = ['year' => (int) $year, 'month' => (int) $monthNumber, 'days' => 0, 'temps' => [], 'winds' => [], 'gusts' => []];
```

and immediately after that `if` block, increment it:

```php
            $yearMonthMap[$key]['days']++;
```

- [ ] **Step 5: Write climate rows only for complete months**

Replace the whole `foreach ($yearMonthMap as $row)` block (lines 125-141) with:

```php
        // A climate row must represent a COMPLETE calendar month: the charts
        // average year-rows with equal weight, so a partial month would count
        // as much as a full one — a 4-day stub of July 2023 was inflating
        // Langebaan's typical July by ~8%.
        //
        // Completeness is tested by counting the days we actually received,
        // not by the date range: Open-Meteo's archive lags real time by a few
        // days, so a month can sit inside the window and still be missing its
        // tail. A month that falls short is simply skipped and picked up by a
        // later fetch — for climatology, omitting a month beats averaging a
        // partial one.
        //
        // (The daily sailable layer below deliberately keeps partial months —
        // it is coverage-normalised and handles them correctly.)
        foreach ($yearMonthMap as $row) {
            $daysInMonth = Carbon::create($row['year'], $row['month'], 1)->daysInMonth;
            if ($row['days'] < $daysInMonth) {
                continue;
            }

            $ktsWind = round($average($row['winds']), 1);
            $ktsGust = round($average($row['gusts']), 1);

            WeatherRecord::updateOrCreate(
                ['spot_guide_id' => $spot->id, 'year' => $row['year'], 'month' => $row['month']],
                [
                    'avg_temp' => round($average($row['temps']), 1),
                    'kts_wind' => $ktsWind,
                    'kts_gust' => $ktsGust,
                    'mph_wind' => (int) round($ktsWind * 1.15078),
                    'mph_gust' => (int) round($ktsGust * 1.15078),
                    'kph_wind' => (int) round($ktsWind * 1.852),
                    'kph_gust' => (int) round($ktsGust * 1.852),
                ]
            );
        }
```

Add the Carbon import at the top of the file, after the existing `use` statements (line 15 area):

```php
use Illuminate\Support\Carbon;
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter=WeatherFetcherClimateMonthsTest`

Expected: PASS, both tests.

- [ ] **Step 7: Run the full suite**

Run: `php artisan test`

Expected: all pass. `WeatherFetcherSailableDaysTest` must still pass — it asserts on the daily layer, which this task does not touch.

- [ ] **Step 8: Commit**

```bash
git add app/Services/WeatherFetcher.php tests/Feature/WeatherFetcherClimateMonthsTest.php
git commit -m "Only store complete calendar months as climate rows"
```

---

### Task 2: Replace a spot's climate rows per fetch so stale rows can't survive

**Files:**
- Modify: `app/Services/WeatherFetcher.php` (the monthly write block from Task 1)
- Test: `tests/Feature/WeatherFetcherClimateMonthsTest.php` (add a test)

**Interfaces:**
- Consumes: the `days` counter, the completeness guard, and the `Carbon` import from Task 1.
- Produces: `fetchForSpot` now leaves a spot's `weather_records` containing *exactly* the complete months in the current window — nothing older, nothing partial.

**Why this task exists:** Task 1 stops *new* bad rows. It does not remove the ones already frozen in the database — rows outside the rolling window are never re-fetched, so `updateOrCreate` can never correct them. Replacing per spot makes a single re-fetch self-healing and means no data migration is needed.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/WeatherFetcherClimateMonthsTest.php`:

```php
    public function test_it_removes_climate_rows_that_fell_out_of_the_window(): void
    {
        Sleep::fake();

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        // A frozen row from an older fetch, now far outside the 3-year window.
        // Nothing re-fetches it, so updateOrCreate can never correct it — it
        // would keep voting in the cross-year average forever.
        \App\Models\WeatherRecord::create([
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYears(5)->year,
            'month' => 7,
            'avg_temp' => 30.0,
            'kts_wind' => 99.0,
            'kts_gust' => 99.0,
            'mph_wind' => 114,
            'mph_gust' => 114,
            'kph_wind' => 183,
            'kph_gust' => 183,
        ]);

        $completeMonth = now()->subMonths(6)->startOfMonth();

        $this->fakeArchive($this->fullMonthReadings($completeMonth, 20.0, 10.0, 15.0));

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $this->assertDatabaseMissing('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYears(5)->year,
            'month' => 7,
        ]);

        $this->assertDatabaseHas('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) $completeMonth->year,
            'month' => (int) $completeMonth->month,
        ]);
    }

    public function test_a_failed_fetch_does_not_wipe_existing_climate_rows(): void
    {
        Sleep::fake();

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        \App\Models\WeatherRecord::create([
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYear()->year,
            'month' => 3,
            'avg_temp' => 18.0,
            'kts_wind' => 12.0,
            'kts_gust' => 18.0,
            'mph_wind' => 14,
            'mph_gust' => 21,
            'kph_wind' => 22,
            'kph_gust' => 33,
        ]);

        // The API fails partway, so fetchForSpot throws before writing anything.
        // The delete must not have happened — a failed fetch leaving a spot with
        // no climate data would blank its charts.
        Http::fake(['archive-api.open-meteo.com/*' => Http::response('boom', 500)]);

        try {
            app(WeatherFetcher::class)->fetchForSpot($spot);
            $this->fail('Expected the fetch to throw on a 500.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertDatabaseHas('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) now()->subYear()->year,
            'month' => 3,
        ]);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=WeatherFetcherClimateMonthsTest`

Expected: `test_it_removes_climate_rows_that_fell_out_of_the_window` FAILS — the out-of-window row survives. `test_a_failed_fetch_does_not_wipe_existing_climate_rows` should already PASS (it pins behaviour the next step must preserve).

- [ ] **Step 3: Collect rows first, then replace inside a transaction**

Replace the `foreach ($yearMonthMap as $row)` block written in Task 1 with:

```php
        // A climate row must represent a COMPLETE calendar month: the charts
        // average year-rows with equal weight, so a partial month would count
        // as much as a full one — a 4-day stub of July 2023 was inflating
        // Langebaan's typical July by ~8%.
        //
        // Completeness is tested by counting the days we actually received,
        // not by the date range: Open-Meteo's archive lags real time by a few
        // days, so a month can sit inside the window and still be missing its
        // tail. A month that falls short is simply skipped and picked up by a
        // later fetch — for climatology, omitting a month beats averaging a
        // partial one.
        //
        // (The daily sailable layer below deliberately keeps partial months —
        // it is coverage-normalised and handles them correctly.)
        $now = now();

        $climateRows = [];
        foreach ($yearMonthMap as $row) {
            $daysInMonth = Carbon::create($row['year'], $row['month'], 1)->daysInMonth;
            if ($row['days'] < $daysInMonth) {
                continue;
            }

            $ktsWind = round($average($row['winds']), 1);
            $ktsGust = round($average($row['gusts']), 1);

            $climateRows[] = [
                'spot_guide_id' => $spot->id,
                'year' => $row['year'],
                'month' => $row['month'],
                'avg_temp' => round($average($row['temps']), 1),
                'kts_wind' => $ktsWind,
                'kts_gust' => $ktsGust,
                'mph_wind' => (int) round($ktsWind * 1.15078),
                'mph_gust' => (int) round($ktsGust * 1.15078),
                'kph_wind' => (int) round($ktsWind * 1.852),
                'kph_gust' => (int) round($ktsGust * 1.852),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Replace rather than merge. Rows that have fallen out of the rolling
        // window are never re-fetched, so updateOrCreate can never correct a
        // stale or partial one — it would vote in the cross-year average
        // forever. Replacing makes a single re-fetch self-healing, which is
        // why this needs no data migration. Guarded by the collect-then-write
        // order above: a failed API call throws before we reach here, so a
        // failure can never leave a spot with no climate data.
        if ($climateRows !== []) {
            DB::transaction(function () use ($spot, $climateRows) {
                WeatherRecord::where('spot_guide_id', $spot->id)->delete();
                WeatherRecord::insert($climateRows);
            });
        }
```

Add the DB facade import alongside the others:

```php
use Illuminate\Support\Facades\DB;
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=WeatherFetcherClimateMonthsTest`

Expected: PASS, all four tests.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/WeatherFetcher.php tests/Feature/WeatherFetcherClimateMonthsTest.php
git commit -m "Replace a spot's climate rows per fetch so stale rows self-heal"
```

---

### Task 3: Stop days with no readings dragging a month's average toward zero

**Files:**
- Modify: `app/Services/WeatherFetcher.php:114-123` (the daily → year-month rollup)
- Test: `tests/Feature/WeatherFetcherClimateMonthsTest.php` (add a test)

**Interfaces:**
- Consumes: nothing new.
- Produces: no signature change. A day contributes to a metric's monthly mean only when it actually has readings for that metric.

**Why this task exists:** `$average` returns `0.0` for an empty array, and the rollup pushes that result unconditionally. A day where Open-Meteo returned nulls for one metric (but not others) therefore contributes a literal `0.0` to that metric's monthly list, silently pulling the average down. Separate bug from Tasks 1-2, same code path.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/WeatherFetcherClimateMonthsTest.php`:

```php
    public function test_a_day_with_no_temperature_readings_does_not_drag_the_month_average_down(): void
    {
        Sleep::fake();

        // A complete month where ONE day has null temperatures but valid wind —
        // an Open-Meteo gap. avg_temp must be 20.0 (the mean of the days that
        // actually reported), not dragged down by a phantom 0.0 for that day.
        // The day still counts toward completeness: it has readings, just not
        // for this metric.
        $completeMonth = now()->subMonths(6)->startOfMonth();
        $readings = $this->fullMonthReadings($completeMonth, 20.0, 10.0, 15.0);

        // Null out the temperature on the second day's two readings (indexes
        // 2 and 3 — two readings per day, day one occupies 0 and 1).
        $readings[2][1] = null;
        $readings[3][1] = null;

        $this->fakeArchive($readings);

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $record = \App\Models\WeatherRecord::where('spot_guide_id', $spot->id)
            ->where('year', (int) $completeMonth->year)
            ->where('month', (int) $completeMonth->month)
            ->first();

        $this->assertNotNull($record);
        $this->assertEqualsWithDelta(20.0, (float) $record->avg_temp, 0.01);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_a_day_with_no_temperature_readings`

Expected: FAIL — `avg_temp` comes back as `10.0` (the mean of `20.0` and a phantom `0.0`) instead of `20.0`.

- [ ] **Step 3: Skip empty metrics in the rollup**

In `app/Services/WeatherFetcher.php`, replace the three unconditional pushes in the daily → year-month rollup:

```php
            $yearMonthMap[$key]['temps'][] = $average($values['temps']);
            $yearMonthMap[$key]['winds'][] = $average($values['winds']);
            $yearMonthMap[$key]['gusts'][] = $average($values['gusts']);
```

with:

```php
            // Only contribute a metric the day actually has readings for.
            // $average returns 0.0 for an empty array, and pushing that would
            // silently drag the month's mean toward zero whenever Open-Meteo
            // returns nulls for one metric but not the others.
            if ($values['temps'] !== []) {
                $yearMonthMap[$key]['temps'][] = $average($values['temps']);
            }
            if ($values['winds'] !== []) {
                $yearMonthMap[$key]['winds'][] = $average($values['winds']);
            }
            if ($values['gusts'] !== []) {
                $yearMonthMap[$key]['gusts'][] = $average($values['gusts']);
            }
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=WeatherFetcherClimateMonthsTest`

Expected: PASS, all five tests.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/WeatherFetcher.php tests/Feature/WeatherFetcherClimateMonthsTest.php
git commit -m "Skip metrics with no readings when rolling days into months"
```

---

### Task 4: Re-fetch local data, verify the correction, and update the docs

**Files:**
- Modify: `CLAUDE.md` (the sailable-days/climate note, ~line 470)
- Modify: `docs/TODO.md` (the prod re-fetch item)

**Interfaces:**
- Consumes: the corrected `WeatherFetcher` from Tasks 1-3.
- Produces: nothing code-facing.

- [ ] **Step 1: Re-fetch local data**

The fix is self-healing but only takes effect on the next fetch. Run it against the local dev database:

```bash
php artisan weather:fetch
```

Expected: a `✓` per spot. This takes a few minutes — it is ~13 API calls per spot with rate-limit pauses.

- [ ] **Step 2: Verify every month now has equal-weight, complete rows**

```bash
php artisan tinker --execute="foreach (\App\Models\SpotGuide::has('weatherRecords')->get() as \$g) { \$odd = \$g->weatherRecords->groupBy('month')->filter(fn(\$r) => \$r->count() != 3); echo \$g->title.': '.(\$odd->isEmpty() ? 'OK — 3 rows every month' : \$odd->map(fn(\$r,\$m) => \"month \$m=\".\$r->count())->implode(', ')).PHP_EOL; }"
```

Expected: `OK — 3 rows every month` for every spot. Before the fix, every spot reported `month 7=4 rows`, and Karpathos and Le Morne reported five contaminated months each.

- [ ] **Step 3: Confirm the July figure moved**

```bash
php artisan tinker --execute="\$g = \App\Models\SpotGuide::where('slug','langebaan')->first(); foreach (\$g->weatherRecords->where('month',7)->sortBy('year') as \$r) { echo \$r->year.' = '.\$r->kts_wind.' kts'.PHP_EOL; } echo 'typical July: '.round(\$g->weatherRecords->where('month',7)->avg('kts_wind'),1).' kts'.PHP_EOL;"
```

Expected: three rows (2023, 2024, 2025), no 4-day 2023 stub, and a typical-July figure near **10.7 kts** rather than the inflated **11.6 kts**.

- [ ] **Step 4: Update CLAUDE.md**

In the sailable-days bullet near the end of `CLAUDE.md`, add after the existing sentence about coverage normalisation:

```markdown
  **The monthly `weather_records` layer is separate and has a different rule: it stores only COMPLETE calendar months.** The `/destinations` wind/temp charts average year-rows with equal weight, so a partial month would count as much as a full one — a 4-day stub once inflated Langebaan's typical July by ~8%. `WeatherFetcher` starts its window on a month boundary, skips the (always partial) current month, and *replaces* a spot's rows each fetch so stale rows that fell out of the rolling window self-heal. Do not "fix" this by having the charts read the daily table: `spot_sailable_days` stores the day's 2nd-highest hour, an order statistic, not a daily mean.
```

- [ ] **Step 5: Update docs/TODO.md**

In the "Sailable-days ranking (follow-ups)" section at the top, replace the first item's parenthetical with a note that the re-fetch is now also required to correct the climate averages:

```markdown
- [ ] **Prod data on partial data** — the admin "Fetch all weather" only populated a few spots (suspected Cloud queue-worker timeout on the batched `FetchAllWeatherJob`). Run a full **synchronous** `php artisan weather:fetch` on Cloud; if it still fails per-spot, capture the `✓/✗` output and either chunk the button into per-spot queued jobs or lengthen the worker timeout. **This re-fetch is now also what corrects the climate averages** — the partial-month fix is self-healing but only applies on the next fetch, so until it runs, prod charts keep the inflated figures.
```

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add CLAUDE.md docs/TODO.md
git commit -m "Document the complete-months rule for climate averages"
```

---

## Out of scope, deliberately

**`mph_*` / `kph_*` double rounding.** These are stored as pre-rounded integers, then averaged and rounded again by the controller, so the mph/kph curves are twice-rounded and won't exactly equal the kts curve converted. The honest fix is to drop the four derived columns and convert client-side from kts (the frontend already has `ktsToUnit()`), but that is a schema change plus a frontend change and does not belong in a correctness fix. Worth raising as its own follow-up.

**Pruning `spot_sailable_days`.** It also grows past three years, but that layer is coverage-normalised, so extra history only improves the rate estimate. Pruning would discard useful data.

**Changing `DestinationController`.** Once every row is a complete month, its plain `->avg()` is correct. No read-path change is needed, and adding weighting logic there would duplicate what the write path now guarantees.

## Before the PR

Run `reconcile-everything` on the branch so the reconcile docs ride in the same PR (project rule — folded reconcile is the default here).

---

### Task 5: Exclude the current month explicitly (added during execution)

**Why this task exists.** Task 4's verification surfaced it on real data. Open-Meteo's archive endpoint **forecast-fills the current day** — a request made at 09:31 on 2026-07-31 returned all 24 hours of that day, including hours not yet elapsed. So on the last day of a month, the current month satisfies the day-count completeness test and is written as though it were observed climate. The plan's Architecture claims the fetcher "skips the (always partial) current month"; the day-count rule alone does not enforce that, it only appears to on days that are not month-end.

Two consequences worth fixing: forecast values enter a climatology table, and the number of rows per month becomes dependent on what day you fetch. Requiring a month to have fully elapsed makes intent match behaviour and yields a stable three complete rows for every month.

**Files:**
- Modify: `app/Services/WeatherFetcher.php` (the completeness gate added in Task 1, rewritten in Task 2)
- Test: `tests/Feature/WeatherFetcherClimateMonthsTest.php`

**Interfaces:**
- Consumes: the `days` counter and `$climateRows` collect-then-replace loop from Tasks 1-2.
- Produces: no signature change. A climate row now requires the month to be both fully elapsed and fully received.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/WeatherFetcherClimateMonthsTest.php`:

```php
    public function test_it_skips_the_current_month_even_when_every_day_is_present(): void
    {
        Sleep::fake();

        // Open-Meteo forecast-fills the current day, so on the last day of a
        // month the archive returns every day of it. Day-count completeness
        // alone would therefore accept the current month and write forecast
        // values into a table of observed climate. The month must also have
        // ELAPSED.
        $currentMonth = now()->startOfMonth();

        $this->fakeArchive($this->fullMonthReadings($currentMonth, 25.0, 30.0, 40.0));

        $spot = SpotGuide::factory()->create(['latitude' => 38.7, 'longitude' => 20.6]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $this->assertDatabaseMissing('weather_records', [
            'spot_guide_id' => $spot->id,
            'year' => (int) $currentMonth->year,
            'month' => (int) $currentMonth->month,
        ]);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=test_it_skips_the_current_month_even_when_every_day_is_present`

Expected: FAIL — the current month is written, because every one of its days is present in the fixture.

- [ ] **Step 3: Require the month to have elapsed**

In `app/Services/WeatherFetcher.php`, in the `foreach ($yearMonthMap as $row)` loop that builds `$climateRows`, replace the completeness gate:

```php
            $daysInMonth = Carbon::create($row['year'], $row['month'], 1)->daysInMonth;
            if ($row['days'] < $daysInMonth) {
                continue;
            }
```

with:

```php
            $monthStart = Carbon::create($row['year'], $row['month'], 1);

            // The month must have ELAPSED. Day-count completeness alone is not
            // enough: Open-Meteo forecast-fills the current day, so on the last
            // day of a month every day is present and the month would qualify
            // on forecast values rather than observations.
            if ($monthStart->gte($currentMonthStart)) {
                continue;
            }

            if ($row['days'] < $monthStart->daysInMonth) {
                continue;
            }
```

and define `$currentMonthStart` alongside `$now`, just above the loop:

```php
        $currentMonthStart = now()->startOfMonth();
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test --filter=WeatherFetcherClimateMonthsTest`

Expected: PASS, all six tests.

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/WeatherFetcher.php tests/Feature/WeatherFetcherClimateMonthsTest.php
git commit -m "Require a climate month to have elapsed, not just be fully received"
```
