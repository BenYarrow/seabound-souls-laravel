# Weather Fetch Triggers — Design

**Date:** 2026-07-09
**Status:** Approved (pending spec review)

## Goal

Let admins refresh historical weather data without waiting for the weekly
scheduled command. Two triggers:

1. **Auto-on-create** — creating a new spot guide (with coordinates)
   automatically queues a weather fetch for that spot.
2. **Manual "Fetch all weather" button** on the admin dashboard — queues a
   fetch for every spot, keeping all spots in sync with each other.

Both run on the queue and report completion via an in-app notification.

## Background

Weather data is historical monthly averages (temp / wind / gust) stored in
`weather_records`, sourced from the Open-Meteo archive API. Today the only way
to populate it is the `weather:fetch` artisan command
(`app/Console/Commands/WeatherFetch.php`), scheduled weekly
(`routes/console.php`: Sundays 03:00). The per-spot fetch logic lives inside
that command's private `processSpotGuide()` — it is not reachable from
anywhere but the CLI.

A single-spot fetch takes ~12+ seconds: it makes ~12 chunked Open-Meteo calls
(3-year range split into 3-month windows) with deliberate `sleep()` pauses to
respect rate limits. This is why the triggers must run on the queue, not
synchronously in an admin HTTP request.

Infra already present: `QUEUE_CONNECTION=database` with the `jobs` table
migrated. **Not** yet present: a running queue worker, and the `notifications`
table (needed for Filament database notifications).

## Decisions

- **Triggers:** auto-on-create **and** a manual dashboard button.
- **Re-fetch on coordinate edits of an existing spot:** **No** — create only.
  Editing coordinates later is handled via the dashboard button / weekly
  schedule.
- **Manual control location:** a single dashboard "Fetch all" button (not
  per-spot), chosen so spots don't drift out of sync with each other.
- **Execution:** queued jobs + in-app notification on completion.
- **Notification channel:** in-app Filament bell only (no email — production
  mail is not live yet).
- **Required fields:** `latitude` / `longitude` become required on the spot
  guide form (the fetch needs only these two fields). This closes the gap
  where a spot could be saved with no coordinates and silently never get
  weather data.

## Architecture

Extract the per-spot fetch logic into a reusable service, then three callers
share it. This is the core DRY move — the logic currently has exactly one
entry point (the CLI command).

```
                         ┌─────────────────────────┐
weather:fetch (weekly) ──▶                          │
FetchSpotWeatherJob     ──▶   WeatherFetcher         │──▶ weather_records
FetchAllWeatherJob      ──▶   ::fetchForSpot(guide)  │    (upsert)
                         └─────────────────────────┘
```

## Components

| Unit | Responsibility |
|------|----------------|
| `app/Services/WeatherFetcher.php` | `fetchForSpot(SpotGuide $spot): void` — the Open-Meteo chunked fetch + monthly-average aggregation + `WeatherRecord::updateOrCreate` upsert, moved verbatim from `WeatherFetch::processSpotGuide`. Single source of truth. Idempotent (upsert). |
| `app/Jobs/FetchSpotWeatherJob.php` | Queued job for one spot. If the spot has no lat/long → log and no-op. Otherwise call `WeatherFetcher::fetchForSpot`. Dispatched by auto-on-create. |
| `app/Jobs/FetchAllWeatherJob.php` | Queued job. Iterates all spots that have coordinates, using the existing paced batching (chunks of 3, `sleep(2)` between batches) to respect Open-Meteo limits. On completion, sends a Filament database notification to the owner. Dispatched by the dashboard button. |
| `app/Console/Commands/WeatherFetch.php` | Refactored to loop spots and call `WeatherFetcher::fetchForSpot`. External behaviour (signature, `--spot`, weekly schedule, console output) unchanged. |
| `app/Models/SpotGuide.php` (`booted()`) | Add a `static::created` hook: if lat/long present → `FetchSpotWeatherJob::dispatch($guide)`. |
| `app/Filament/Widgets/WeatherFetchWidget.php` (+ blade view) | Dashboard widget with a "Fetch all weather" button. Action dispatches `FetchAllWeatherJob` and flashes "Fetch started — you'll be notified when it finishes." |
| `app/Filament/Resources/SpotGuideResource.php` | `latitude` / `longitude` → `->required()` with range rules (`minValue(-90)->maxValue(90)` and `minValue(-180)->maxValue(180)`). |
| `database/migrations/*_create_notifications_table.php` | Generated via `php artisan notifications:table` — backs the Filament bell notification. |

## Data Flow

**Create path:** admin creates a spot guide (coords now required) →
`SpotGuide::created` fires → `FetchSpotWeatherJob` queued → worker runs
`WeatherFetcher::fetchForSpot` → `weather_records` upserted for that spot.

**Manual path:** admin clicks "Fetch all weather" on the dashboard →
`FetchAllWeatherJob` queued, button flashes a "started" message → worker
loops every spot-with-coords (paced) → `weather_records` upserted →
**notification** "Weather data updated for N spots" appears in the admin bell.

## Error Handling

- `FetchSpotWeatherJob` guards missing coordinates: logs and returns without
  error (a spot without coords is a no-op, not a failure).
- Per-spot Open-Meteo failures inside `FetchAllWeatherJob` are caught and
  logged so one bad spot doesn't abort the whole batch (mirrors the command's
  current try/catch behaviour).
- Jobs set `$tries = 3` and a backoff so a transient API blip retries rather
  than dying. `WeatherFetcher::fetchForSpot` still throws on a hard API error
  so the job's retry/failure machinery engages.

## Testing (TDD, fully mocked)

All tests use `Http::fake()` (Open-Meteo), `Queue::fake()`, and
`Notification::fake()` — no network, no real queue, no live mail.

1. `SpotGuide` created **with** coordinates dispatches `FetchSpotWeatherJob`.
2. `SpotGuide` created **without** coordinates does **not** dispatch the job.
3. `WeatherFetcher::fetchForSpot` upserts the correct `weather_records` rows
   from a faked Open-Meteo response.
4. `WeatherFetcher::fetchForSpot` is idempotent — running twice updates rows
   rather than duplicating them.
5. `FetchSpotWeatherJob` no-ops (no HTTP call) when the spot has no coords.
6. The dashboard widget action dispatches `FetchAllWeatherJob`.
7. `FetchAllWeatherJob` sends the completion notification to the owner.
8. `latitude` / `longitude` are required and range-validated on the
   `SpotGuideResource` form.
9. The refactored `weather:fetch` command still populates records (regression
   guard on the extraction).

## Infra / Ops (folded into this branch)

- Run `php artisan notifications:table` and migrate.
- Document (in `CLAUDE.md` session-start notes + `SITREP.md`) that a queue
  worker must run for the triggers to do anything: `php artisan queue:work`
  locally, a worker process in production (e.g. Laravel Cloud worker). With
  the `database` driver and no worker, dispatched jobs sit unprocessed in the
  `jobs` table.

## Out of Scope

- Per-spot manual fetch button (dashboard "fetch all" only, by decision).
- Re-fetch on coordinate edits (create-only, by decision).
- Email notifications (in-app only; revisit when production mail is live).
- Backfilling coordinates on any existing coordinate-less spots (the fetch
  jobs simply skip spots without coords).
