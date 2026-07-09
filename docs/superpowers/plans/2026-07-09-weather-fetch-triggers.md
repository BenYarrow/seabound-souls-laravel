# Weather Fetch Triggers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins refresh historical weather data on demand — automatically when a spot guide is created, and via a dashboard "Fetch all weather" button — instead of waiting for the weekly scheduled command.

**Architecture:** Extract the per-spot Open-Meteo fetch logic out of the `weather:fetch` command into a reusable `WeatherFetcher` service. Three callers share it: the weekly command, a per-spot queued job (`FetchSpotWeatherJob`, dispatched by a `SpotGuide::created` hook), and a fetch-all queued job (`FetchAllWeatherJob`, dispatched by a Filament dashboard widget). The fetch-all job posts an in-app Filament notification on completion.

**Tech Stack:** Laravel 12, Filament v3.3, Livewire v3, PHPUnit 11, SQLite in-memory (tests), database queue driver, Open-Meteo archive API (mocked in tests via `Http::fake`).

## Global Constraints

- **JSDoc/PHPDoc on every method**, explaining *why* where non-obvious (project rule).
- **No single-letter variables** (except `i` in trivial loops); no cryptic abbreviations.
- **Module header comment** at the top of each new source file describing its role.
- **TDD**: write the failing test first, watch it fail, implement, watch it pass, commit.
- **All external I/O mocked in tests**: `Http::fake()` (Open-Meteo), `Sleep::fake()` (pacing), `Queue::fake()` (dispatch assertions), `Notification::fake()` where asserting notifications — no network, no real sleeps.
- **`php artisan test` must pass fully** before any task is considered done.
- Run node/artisan with the project Node when needed: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH"` (only relevant if building assets; not expected here).
- Auto-fetch is **create-only** (not on coordinate edits). Notifications are **in-app bell only** (no email).
- The fetch requires **only** `latitude` + `longitude` from a spot guide.

---

### Task 1: Extract `WeatherFetcher` service

Move the per-spot fetch logic out of the command into a service so jobs can reuse it. Replace raw `sleep()` with Laravel's `Sleep` helper so tests can fake pacing. No external behaviour change to the command.

**Files:**
- Create: `app/Services/WeatherFetcher.php`
- Modify: `app/Console/Commands/WeatherFetch.php` (replace private `processSpotGuide` + batching loop with calls into the service)
- Test: `tests/Unit/WeatherFetcherTest.php`

**Interfaces:**
- Produces:
  - `WeatherFetcher::fetchForSpot(SpotGuide $spot): void` — fetches 3 years of Open-Meteo archive data (in 3-month chunks, paced with `Sleep`), aggregates to monthly averages, and upserts `WeatherRecord` rows for the spot. Idempotent.
  - `WeatherFetcher::fetchForSpots(iterable $spots, ?callable $reporter = null): int` — iterates spots in batches of 3 with a 2-second pause between batches, calling `fetchForSpot` per spot inside a try/catch. Invokes `$reporter($spot, $succeeded, $errorMessage)` after each if provided. Returns the count of spots processed successfully.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Unit tests for App\Services\WeatherFetcher — the shared Open-Meteo fetch +
// monthly-average upsert used by the weekly command and the fetch jobs.

namespace Tests\Unit;

use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use App\Services\WeatherFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class WeatherFetcherTest extends TestCase
{
    /** Two days of hourly readings in Feb 2024, all inside the 9am–7pm window. */
    private function fakeArchiveResponse(): array
    {
        $times = [];
        $temps = [];
        $winds = [];
        $gusts = [];

        foreach (['2024-02-10', '2024-02-11'] as $date) {
            foreach (range(9, 19) as $hour) {
                $times[] = sprintf('%sT%02d:00', $date, $hour);
                $temps[] = 20.0;
                $winds[] = 15.0;
                $gusts[] = 22.0;
            }
        }

        return ['hourly' => ['time' => $times, 'temperature_2m' => $temps, 'wind_speed_10m' => $winds, 'wind_gusts_10m' => $gusts]];
    }

    public function test_fetch_for_spot_upserts_monthly_averages(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);

        $spot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);

        app(WeatherFetcher::class)->fetchForSpot($spot);

        $record = WeatherRecord::where('spot_guide_id', $spot->id)->where('year', 2024)->where('month', 2)->first();
        $this->assertNotNull($record);
        $this->assertEquals(20.0, (float) $record->avg_temp);
        $this->assertEquals(15.0, (float) $record->kts_wind);
        $this->assertEquals(22.0, (float) $record->kts_gust);
    }

    public function test_fetch_for_spot_is_idempotent(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);

        $spot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);
        $fetcher = app(WeatherFetcher::class);

        $fetcher->fetchForSpot($spot);
        $fetcher->fetchForSpot($spot);

        // One row per (spot, year, month) — the second run updates, not duplicates.
        $this->assertSame(1, WeatherRecord::where('spot_guide_id', $spot->id)->where('year', 2024)->where('month', 2)->count());
    }

    public function test_fetch_for_spots_reports_and_counts(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);

        $spots = SpotGuide::factory()->count(2)->create(['latitude' => 36.0, 'longitude' => -6.0]);
        $reported = [];

        $processed = app(WeatherFetcher::class)->fetchForSpots($spots, function (SpotGuide $spot, bool $ok) use (&$reported) {
            $reported[$spot->id] = $ok;
        });

        $this->assertSame(2, $processed);
        $this->assertSame([true, true], array_values($reported));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WeatherFetcherTest`
Expected: FAIL — `Class "App\Services\WeatherFetcher" not found`.

- [ ] **Step 3: Create the service**

Create `app/Services/WeatherFetcher.php`. The `fetchForSpot` body is moved verbatim from `WeatherFetch::processSpotGuide`, with `sleep(1)` replaced by `Sleep::for(1)->second()`. `fetchForSpots` holds the batching/pacing/try-catch previously in the command's `handle`.

```php
<?php

// Shared Open-Meteo fetch service. Pulls 3 years of hourly archive data for a
// spot guide, reduces it to monthly averages (temperature / wind / gust) over
// the 9am–7pm sailing window, and upserts WeatherRecord rows. Single source of
// truth for the weekly weather:fetch command and the FetchSpotWeather /
// FetchAllWeather queued jobs — none of them re-implement the fetch.

namespace App\Services;

use App\Models\SpotGuide;
use App\Models\WeatherRecord;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class WeatherFetcher
{
    /**
     * Fetch, aggregate, and upsert monthly weather averages for one spot.
     *
     * Splits the 3-year range into 3-month windows because a single large
     * request to the archive API intermittently 500s; pauses between windows
     * (via the fakeable Sleep helper) to stay within Open-Meteo rate limits.
     */
    public function fetchForSpot(SpotGuide $spot): void
    {
        $times = [];
        $temps = [];
        $winds = [];
        $gusts = [];

        $startOfRange = now()->subYears(3);
        $endOfRange = now();
        $chunkStart = $startOfRange->copy();
        $isFirstChunk = true;

        while ($chunkStart->lt($endOfRange)) {
            $chunkEnd = $chunkStart->copy()->addMonths(3);
            if ($chunkEnd->gt($endOfRange)) {
                $chunkEnd = $endOfRange->copy();
            }

            if (! $isFirstChunk) {
                Sleep::for(1)->second();
            }
            $isFirstChunk = false;

            $response = Http::get('https://archive-api.open-meteo.com/v1/archive', [
                'latitude' => $spot->latitude,
                'longitude' => $spot->longitude,
                'start_date' => $chunkStart->format('Y-m-d'),
                'end_date' => $chunkEnd->format('Y-m-d'),
                'hourly' => 'temperature_2m,wind_speed_10m,wind_gusts_10m',
                'wind_speed_unit' => 'kn',
                'timezone' => 'auto',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException("API error: {$response->status()} for {$chunkStart->format('Y-m-d')} to {$chunkEnd->format('Y-m-d')}");
            }

            $hourlyData = $response->json()['hourly'] ?? [];
            $times = array_merge($times, $hourlyData['time'] ?? []);
            $temps = array_merge($temps, $hourlyData['temperature_2m'] ?? []);
            $winds = array_merge($winds, $hourlyData['wind_speed_10m'] ?? []);
            $gusts = array_merge($gusts, $hourlyData['wind_gusts_10m'] ?? []);

            $chunkStart = $chunkEnd->copy();
        }

        // Daily buckets, restricted to the 9am–7pm sailing window.
        $dailyMap = [];
        foreach ($times as $index => $datetime) {
            $hour = (int) substr($datetime, 11, 2);
            if ($hour < 9 || $hour > 19) {
                continue;
            }

            $date = substr($datetime, 0, 10);
            if (! isset($dailyMap[$date])) {
                $dailyMap[$date] = ['temps' => [], 'winds' => [], 'gusts' => []];
            }
            if (isset($temps[$index]) && $temps[$index] !== null) {
                $dailyMap[$date]['temps'][] = $temps[$index];
            }
            if (isset($winds[$index]) && $winds[$index] !== null) {
                $dailyMap[$date]['winds'][] = $winds[$index];
            }
            if (isset($gusts[$index]) && $gusts[$index] !== null) {
                $dailyMap[$date]['gusts'][] = $gusts[$index];
            }
        }

        $average = fn (array $values): float => count($values) > 0 ? array_sum($values) / count($values) : 0.0;

        // Roll daily buckets up into year/month buckets.
        $yearMonthMap = [];
        foreach ($dailyMap as $date => $values) {
            [$year, $monthNumber] = explode('-', $date);
            $key = "{$year}-{$monthNumber}";
            if (! isset($yearMonthMap[$key])) {
                $yearMonthMap[$key] = ['year' => (int) $year, 'month' => (int) $monthNumber, 'temps' => [], 'winds' => [], 'gusts' => []];
            }
            $yearMonthMap[$key]['temps'][] = $average($values['temps']);
            $yearMonthMap[$key]['winds'][] = $average($values['winds']);
            $yearMonthMap[$key]['gusts'][] = $average($values['gusts']);
        }

        foreach ($yearMonthMap as $row) {
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
    }

    /**
     * Fetch weather for many spots, paced in batches of 3 with a 2-second gap
     * between batches (Open-Meteo rate-limit protection). Each spot is fetched
     * inside a try/catch so one failure never aborts the batch. Optionally
     * reports per-spot progress. Returns the number of spots fetched OK.
     *
     * @param  iterable<SpotGuide>  $spots
     * @param  (callable(SpotGuide, bool, ?string): void)|null  $reporter
     */
    public function fetchForSpots(iterable $spots, ?callable $reporter = null): int
    {
        $processed = 0;

        foreach (collect($spots)->chunk(3)->values() as $batchIndex => $batch) {
            if ($batchIndex > 0) {
                Sleep::for(2)->seconds();
            }

            foreach ($batch as $spot) {
                try {
                    $this->fetchForSpot($spot);
                    $processed++;
                    if ($reporter) {
                        $reporter($spot, true, null);
                    }
                } catch (\Throwable $exception) {
                    if ($reporter) {
                        $reporter($spot, false, $exception->getMessage());
                    }
                }
            }
        }

        return $processed;
    }
}
```

- [ ] **Step 4: Refactor the command to use the service**

Replace `app/Console/Commands/WeatherFetch.php` so `handle()` delegates to the service and keeps its console output via the reporter callback. Delete the private `processSpotGuide` method and the `MONTH_NAMES` constant (no longer used).

```php
<?php

// Weekly (and ad-hoc) weather refresh command. Thin CLI wrapper over
// App\Services\WeatherFetcher — it selects spot guides and reports progress to
// the console; all fetch/aggregate/upsert logic lives in the service.

namespace App\Console\Commands;

use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Console\Command;

class WeatherFetch extends Command
{
    protected $signature = 'weather:fetch {--spot= : Only fetch for a specific spot guide slug}';

    protected $description = 'Fetch historical weather data from Open-Meteo for all spot guides';

    public function handle(WeatherFetcher $fetcher): int
    {
        $this->info('Starting weather data fetch...');

        $query = SpotGuide::whereNotNull('latitude')->whereNotNull('longitude');
        if ($slug = $this->option('spot')) {
            $query->where('slug', $slug);
        }
        $spotGuides = $query->get();

        if ($spotGuides->isEmpty()) {
            $this->warn('No spot guides with coordinates found.');
            return self::SUCCESS;
        }

        $this->info("Processing {$spotGuides->count()} spot guides...");

        $processed = $fetcher->fetchForSpots($spotGuides, function (SpotGuide $spot, bool $ok, ?string $error): void {
            $ok ? $this->info("✓ {$spot->title}") : $this->error("✗ {$spot->title}: {$error}");
        });

        $this->info("Completed. Processed {$processed} spot guides.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=WeatherFetcherTest`
Expected: PASS (3 tests). The suite should not make network calls and should not actually sleep.

- [ ] **Step 6: Run the full suite (regression on the command extraction)**

Run: `php artisan test`
Expected: PASS — same count as before plus the 3 new tests.

- [ ] **Step 7: Commit**

```bash
git add app/Services/WeatherFetcher.php app/Console/Commands/WeatherFetch.php tests/Unit/WeatherFetcherTest.php
git commit -m "refactor: extract WeatherFetcher service from weather:fetch command

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: `FetchSpotWeatherJob` + auto-on-create hook

Queue a single-spot fetch when a spot guide is created with coordinates. Switch the test queue driver to `array` so this (and any future) queued job never executes inline during unrelated tests.

**Files:**
- Create: `app/Jobs/FetchSpotWeatherJob.php`
- Modify: `app/Models/SpotGuide.php` (add `static::created` to `booted()`)
- Modify: `phpunit.xml` (`QUEUE_CONNECTION` value `sync` → `array`)
- Modify: `CLAUDE.md` (session-start note: a queue worker must run for fetch triggers)
- Test: `tests/Feature/FetchSpotWeatherJobTest.php`

**Interfaces:**
- Consumes: `WeatherFetcher::fetchForSpot(SpotGuide): void` (Task 1).
- Produces: `FetchSpotWeatherJob` with constructor `__construct(public int $spotGuideId)`; `handle(WeatherFetcher $fetcher): void`. Dispatched as `FetchSpotWeatherJob::dispatch($spotGuide->id)`.

- [ ] **Step 1: Change the test queue driver**

In `phpunit.xml`, change:

```xml
<env name="QUEUE_CONNECTION" value="sync"/>
```
to:
```xml
<env name="QUEUE_CONNECTION" value="array"/>
```

Why: with `sync`, the new `created` hook would run the job — and its Open-Meteo HTTP — inline in every test that creates a SpotGuide. With `array`, dispatched jobs are stored and never auto-run; tests assert dispatch via `Queue::fake()` and exercise job bodies by calling `handle()` directly.

- [ ] **Step 2: Write the failing test**

```php
<?php

// Feature tests for the auto-on-create weather trigger and FetchSpotWeatherJob.

namespace Tests\Feature;

use App\Jobs\FetchSpotWeatherJob;
use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FetchSpotWeatherJobTest extends TestCase
{
    public function test_creating_a_spot_with_coordinates_dispatches_the_job(): void
    {
        Queue::fake();

        $spot = SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);

        Queue::assertPushed(FetchSpotWeatherJob::class, fn (FetchSpotWeatherJob $job) => $job->spotGuideId === $spot->id);
    }

    public function test_creating_a_spot_without_coordinates_does_not_dispatch(): void
    {
        Queue::fake();

        SpotGuide::factory()->create(['latitude' => null, 'longitude' => null]);

        Queue::assertNotPushed(FetchSpotWeatherJob::class);
    }

    public function test_job_no_ops_when_spot_has_no_coordinates(): void
    {
        Sleep::fake();
        Http::fake();

        $spot = SpotGuide::factory()->create(['latitude' => null, 'longitude' => null]);

        (new FetchSpotWeatherJob($spot->id))->handle(app(WeatherFetcher::class));

        // Guard clause fired: no Open-Meteo call was made.
        Http::assertNothingSent();
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=FetchSpotWeatherJobTest`
Expected: FAIL — `Class "App\Jobs\FetchSpotWeatherJob" not found`.

- [ ] **Step 4: Create the job**

```php
<?php

// Queued single-spot weather fetch. Dispatched when a spot guide is created
// with coordinates (see SpotGuide::booted). Guards against a spot that has no
// coordinates or was deleted before the worker ran, so it can never hammer the
// API pointlessly or throw on a missing model.

namespace App\Jobs;

use App\Models\SpotGuide;
use App\Services\WeatherFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchSpotWeatherJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry a few times so a transient Open-Meteo blip doesn't lose the fetch. */
    public int $tries = 3;

    /** Seconds to wait between retries. */
    public int $backoff = 10;

    public function __construct(public int $spotGuideId)
    {
    }

    public function handle(WeatherFetcher $fetcher): void
    {
        $spot = SpotGuide::find($this->spotGuideId);

        if (! $spot) {
            return;
        }

        if ($spot->latitude === null || $spot->longitude === null) {
            Log::info("Skipping weather fetch for spot guide {$spot->id} ({$spot->title}): no coordinates.");
            return;
        }

        $fetcher->fetchForSpot($spot);
    }
}
```

- [ ] **Step 5: Add the created hook to the model**

In `app/Models/SpotGuide.php`, extend the existing `booted()` method (keep the current `saving` hook) by adding a `created` hook. Add the `use App\Jobs\FetchSpotWeatherJob;` import at the top.

```php
    protected static function booted(): void
    {
        static::saving(function (SpotGuide $guide) {
            if ($guide->isDirty('country_id')) {
                $guide->country_name = Country::find($guide->country_id)?->name;
            }
        });

        // Auto-fetch weather for a newly created spot as soon as it has
        // coordinates, so admins don't wait for the weekly command. Create-only
        // by design — editing coordinates later is handled by the dashboard
        // "Fetch all weather" button.
        static::created(function (SpotGuide $guide) {
            if ($guide->latitude !== null && $guide->longitude !== null) {
                FetchSpotWeatherJob::dispatch($guide->id);
            }
        });
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=FetchSpotWeatherJobTest`
Expected: PASS (3 tests).

- [ ] **Step 7: Document the queue-worker requirement**

In `CLAUDE.md`, under "Session start" → "Dev servers", add a bullet:

```markdown
- **Queue worker:** the weather-fetch triggers (auto-on-create + dashboard "Fetch all") dispatch to the `database` queue. Run `php artisan queue:work` (or `queue:listen`) or the jobs sit unprocessed in the `jobs` table. In production a worker process must run.
```

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS — all prior tests still green with the `array` queue driver, plus the 3 new tests.

- [ ] **Step 9: Commit**

```bash
git add app/Jobs/FetchSpotWeatherJob.php app/Models/SpotGuide.php phpunit.xml CLAUDE.md tests/Feature/FetchSpotWeatherJobTest.php
git commit -m "feat: auto-fetch weather when a spot guide is created

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: `notifications` table + `FetchAllWeatherJob`

Add the Filament database-notifications table, then a queued job that fetches every spot-with-coordinates and posts an in-app completion notification.

**Files:**
- Create: `database/migrations/<timestamp>_create_notifications_table.php` (via artisan)
- Create: `app/Jobs/FetchAllWeatherJob.php`
- Test: `tests/Feature/FetchAllWeatherJobTest.php`

**Interfaces:**
- Consumes: `WeatherFetcher::fetchForSpots(iterable, ?callable): int` (Task 1).
- Produces: `FetchAllWeatherJob` with constructor `__construct()` (no args); `handle(WeatherFetcher $fetcher): void`. Dispatched as `FetchAllWeatherJob::dispatch()`.

- [ ] **Step 1: Generate the notifications table migration**

Run: `php artisan notifications:table`
Expected: creates `database/migrations/<timestamp>_create_notifications_table.php` (standard Laravel/Filament notifications schema). Do not hand-edit it.

- [ ] **Step 2: Write the failing test**

```php
<?php

// Feature tests for FetchAllWeatherJob — the dashboard "Fetch all" job that
// refreshes every spot with coordinates and notifies the admin on completion.

namespace Tests\Feature;

use App\Jobs\FetchAllWeatherJob;
use App\Models\SpotGuide;
use App\Models\User;
use App\Models\WeatherRecord;
use App\Services\WeatherFetcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FetchAllWeatherJobTest extends TestCase
{
    private function fakeArchiveResponse(): array
    {
        $times = [];
        $temps = [];
        $winds = [];
        $gusts = [];
        foreach (['2024-02-10', '2024-02-11'] as $date) {
            foreach (range(9, 19) as $hour) {
                $times[] = sprintf('%sT%02d:00', $date, $hour);
                $temps[] = 20.0;
                $winds[] = 15.0;
                $gusts[] = 22.0;
            }
        }
        return ['hourly' => ['time' => $times, 'temperature_2m' => $temps, 'wind_speed_10m' => $winds, 'wind_gusts_10m' => $gusts]];
    }

    public function test_fetches_all_spots_with_coordinates(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);
        User::factory()->create();

        // Two spots with coords (single batch, no inter-batch sleep), one without.
        $spots = SpotGuide::factory()->count(2)->create(['latitude' => 36.0, 'longitude' => -6.0]);
        SpotGuide::factory()->create(['latitude' => null, 'longitude' => null]);

        (new FetchAllWeatherJob())->handle(app(WeatherFetcher::class));

        foreach ($spots as $spot) {
            $this->assertDatabaseHas('weather_records', ['spot_guide_id' => $spot->id, 'year' => 2024, 'month' => 2]);
        }
    }

    public function test_notifies_the_admin_on_completion(): void
    {
        Sleep::fake();
        Http::fake(['archive-api.open-meteo.com/*' => Http::response($this->fakeArchiveResponse())]);
        $user = User::factory()->create();
        SpotGuide::factory()->create(['latitude' => 36.0, 'longitude' => -6.0]);

        (new FetchAllWeatherJob())->handle(app(WeatherFetcher::class));

        // Filament stores database notifications on the notifiable's row.
        $this->assertSame(1, $user->notifications()->count());
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test --filter=FetchAllWeatherJobTest`
Expected: FAIL — `Class "App\Jobs\FetchAllWeatherJob" not found`.

- [ ] **Step 4: Create the job**

```php
<?php

// Queued "fetch all" weather refresh. Dispatched by the dashboard widget; keeps
// every spot's data in sync in one paced run, then posts a Filament database
// notification to all admins so they know the refresh finished.

namespace App\Jobs;

use App\Models\SpotGuide;
use App\Models\User;
use App\Services\WeatherFetcher;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchAllWeatherJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** One retry attempt — a full re-run is expensive, so keep retries low. */
    public int $tries = 2;

    public int $backoff = 30;

    public function handle(WeatherFetcher $fetcher): void
    {
        $spots = SpotGuide::whereNotNull('latitude')->whereNotNull('longitude')->get();

        $processed = $fetcher->fetchForSpots($spots);

        Notification::make()
            ->title('Weather data updated')
            ->body("Refreshed weather for {$processed} spot " . ($processed === 1 ? 'guide' : 'guides') . '.')
            ->success()
            ->sendToDatabase(User::all());
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=FetchAllWeatherJobTest`
Expected: PASS (2 tests).

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Jobs/FetchAllWeatherJob.php tests/Feature/FetchAllWeatherJobTest.php
git commit -m "feat: FetchAllWeatherJob with in-app completion notification

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Dashboard "Fetch all weather" widget

Add a Filament dashboard widget with a button that dispatches `FetchAllWeatherJob` and flashes a "started" notification. The panel already auto-discovers widgets in `app/Filament/Widgets`, so no provider change is needed.

**Files:**
- Create: `app/Filament/Widgets/WeatherFetchWidget.php`
- Create: `resources/views/filament/widgets/weather-fetch-widget.blade.php`
- Test: `tests/Feature/Filament/WeatherFetchWidgetTest.php`

**Interfaces:**
- Consumes: `FetchAllWeatherJob::dispatch()` (Task 3).
- Produces: `WeatherFetchWidget` Livewire component with public method `fetchAll(): void`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Feature test for the dashboard "Fetch all weather" widget button.

namespace Tests\Feature\Filament;

use App\Filament\Widgets\WeatherFetchWidget;
use App\Jobs\FetchAllWeatherJob;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class WeatherFetchWidgetTest extends TestCase
{
    public function test_button_dispatches_the_fetch_all_job(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create());

        Livewire::test(WeatherFetchWidget::class)
            ->call('fetchAll');

        Queue::assertPushed(FetchAllWeatherJob::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=WeatherFetchWidgetTest`
Expected: FAIL — `Class "App\Filament\Widgets\WeatherFetchWidget" not found`.

- [ ] **Step 3: Create the widget**

```php
<?php

// Dashboard widget: a single "Fetch all weather" button that queues a refresh
// of every spot guide's weather data. Gives the admin an on-demand trigger
// instead of waiting for the weekly scheduled command.

namespace App\Filament\Widgets;

use App\Jobs\FetchAllWeatherJob;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class WeatherFetchWidget extends Widget
{
    protected static string $view = 'filament.widgets.weather-fetch-widget';

    /** Full width, placed at the top of the dashboard. */
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    /**
     * Queue a fetch-all run and confirm to the admin. Completion itself arrives
     * later as a database notification from FetchAllWeatherJob.
     */
    public function fetchAll(): void
    {
        FetchAllWeatherJob::dispatch();

        Notification::make()
            ->title('Weather fetch started')
            ->body("You'll get a notification here when it finishes.")
            ->success()
            ->send();
    }
}
```

- [ ] **Step 4: Create the widget view**

```blade
{{-- Dashboard "Fetch all weather" button. Queues FetchAllWeatherJob. --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Weather data</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Refresh historical weather for every destination. Runs in the background.
                </p>
            </div>
            <x-filament::button wire:click="fetchAll" wire:loading.attr="disabled" icon="heroicon-o-arrow-path">
                Fetch all weather
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --filter=WeatherFetchWidgetTest`
Expected: PASS (1 test).

- [ ] **Step 6: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Widgets/WeatherFetchWidget.php resources/views/filament/widgets/weather-fetch-widget.blade.php tests/Feature/Filament/WeatherFetchWidgetTest.php
git commit -m "feat: dashboard Fetch all weather button

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Require + range-validate coordinates on the spot-guide form

Make `latitude`/`longitude` required with valid geographic ranges, so a spot guide can't be saved without the coordinates the fetch depends on.

**Files:**
- Modify: `app/Filament/Resources/SpotGuideResource.php:63-66`
- Test: `tests/Feature/Filament/SpotGuideCoordinatesTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing consumed elsewhere.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Feature test: the spot-guide form requires valid coordinates (the weather
// fetch depends on them).

namespace Tests\Feature\Filament;

use App\Filament\Resources\SpotGuideResource\Pages\CreateSpotGuide;
use App\Models\Country;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class SpotGuideCoordinatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    public function test_coordinates_are_required(): void
    {
        Livewire::test(CreateSpotGuide::class)
            ->fillForm([
                'title' => 'No Coords Bay',
                'country_id' => Country::factory()->create()->id,
                'latitude' => null,
                'longitude' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['latitude' => 'required', 'longitude' => 'required']);
    }

    public function test_coordinates_must_be_in_range(): void
    {
        Livewire::test(CreateSpotGuide::class)
            ->fillForm([
                'title' => 'Off World Bay',
                'country_id' => Country::factory()->create()->id,
                'latitude' => 200,
                'longitude' => 500,
            ])
            ->call('create')
            ->assertHasFormErrors(['latitude', 'longitude']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=SpotGuideCoordinatesTest`
Expected: FAIL — form saves without coordinates (no `required`/range errors raised).

- [ ] **Step 3: Add the validation rules**

In `app/Filament/Resources/SpotGuideResource.php`, replace the latitude/longitude fields (lines 63-66):

```php
                            TextInput::make('latitude')
                                ->numeric()
                                ->required()
                                ->minValue(-90)
                                ->maxValue(90)
                                ->helperText('Required — the weather fetch uses this.'),
                            TextInput::make('longitude')
                                ->numeric()
                                ->required()
                                ->minValue(-180)
                                ->maxValue(180)
                                ->helperText('Required — the weather fetch uses this.'),
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=SpotGuideCoordinatesTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/SpotGuideResource.php tests/Feature/Filament/SpotGuideCoordinatesTest.php
git commit -m "feat: require valid coordinates on the spot guide form

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:**
- Shared `WeatherFetcher` service → Task 1 ✅
- Auto-on-create job + hook → Task 2 ✅
- Create-only (not on edit) → Task 2 hook is `static::created` only ✅
- Dashboard "fetch all" button → Task 4 ✅
- `FetchAllWeatherJob` paced + notification → Task 3 ✅
- In-app bell notification only → Task 3 `sendToDatabase` ✅
- notifications table → Task 3 Step 1 ✅
- Required + range-validated lat/long → Task 5 ✅
- Queue-worker documentation → Task 2 Step 7 ✅
- All external I/O mocked (Http/Sleep/Queue/Notification) → every task ✅
- Idempotent upsert → Task 1 test ✅
- Job guards missing coords → Task 2 ✅; per-spot failure isolation → Task 1 `fetchForSpots` try/catch ✅; retries/backoff → Tasks 2 & 3 ✅

**2. Placeholder scan:** No TBD/TODO/"handle edge cases"/"similar to Task N". All code blocks are complete. ✅

**3. Type consistency:** `fetchForSpot(SpotGuide): void` and `fetchForSpots(iterable, ?callable): int` are used identically in Tasks 1, 2, 3. `FetchSpotWeatherJob(int $spotGuideId)` matches the `->spotGuideId` assertion in Task 2's test and the `dispatch($guide->id)` in the hook. `FetchAllWeatherJob::dispatch()` (no args) matches Tasks 3 & 4. `fetchAll()` widget method matches its test. ✅

**Note for executor:** confirm the CreateSpotGuide page namespace is `App\Filament\Resources\SpotGuideResource\Pages\CreateSpotGuide` before running Task 5's test (standard Filament path; adjust the `use` if the generated resource differs).
