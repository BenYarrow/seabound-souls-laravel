---
title: Weather fetch triggers (auto-on-create + dashboard button)
tags: [weather, queue, filament, jobs, admin]
status: stable
completed: 2026-07-09
commits: [819b06b, 10aed88, b283e08, 4ea3ff1, 3d7604e, 75e48d1, f4d04c1, 427beea, 06295a2, 03b8899, da8ddac, ee3e08d]
pr: 14
---

# Weather fetch triggers

## What shipped

On-demand refresh of historical weather data, so an admin no longer waits up to
a week for the scheduled `weather:fetch` command.

- **`App\Services\WeatherFetcher`** — the per-spot Open-Meteo fetch + monthly-average
  upsert logic, extracted from `WeatherFetch::processSpotGuide` into a reusable
  service (`fetchForSpot`, `fetchForSpots`). Now the single source of truth: the
  weekly command, the per-spot job, and the fetch-all job all delegate to it.
  Raw `sleep()` replaced with the fakeable `Illuminate\Support\Sleep` helper.
- **Auto-on-create** — a `SpotGuide::created` hook dispatches `FetchSpotWeatherJob`
  (queued, single spot) when a new guide has coordinates. Create-only by design;
  editing coordinates later is handled by the dashboard button / weekly schedule.
- **Dashboard "Fetch all weather" button** — a Filament dashboard widget
  (`WeatherFetchWidget`) dispatches `FetchAllWeatherJob`, which refreshes every
  spot-with-coordinates (paced in batches of 3) and posts an in-app Filament bell
  notification on completion. Keeps all spots in sync with each other.
- **`notifications` table** — added (Filament database notifications).
- **Required coordinates** — `latitude`/`longitude` are now required and
  range-validated (`-90..90` / `-180..180`) on the spot-guide form, closing the
  gap where a spot could be saved with no coordinates and silently never get
  weather.

Design spec: `docs/superpowers/specs/2026-07-09-weather-fetch-triggers-design.md`.
Plan: `docs/superpowers/plans/2026-07-09-weather-fetch-triggers.md`.

## Findings worth keeping

- **Laravel 12 has no built-in `array` queue connector.** To stop the new
  `created` hook running the job's real Open-Meteo HTTP inline during unrelated
  tests, the test env uses `QUEUE_CONNECTION=array` mapped to the `null` driver
  via a new `array` connection in `config/queue.php`. Dispatched jobs are
  discarded (never executed); `Queue::fake()` still asserts dispatch because it
  intercepts before the driver. Inert in production (which uses `database`).
- **Filament DB notifications implement `ShouldQueue`.** `sendToDatabase()` would
  be re-queued and silently discarded by the null test driver, so `FetchAllWeatherJob`
  uses `notifyNow(...)` to write the notification synchronously inside the worker
  it's already running on. Writes the correct Filament-format payload, so the
  admin bell renders it.
- **No dispatch-after-commit race today.** The Filament panel does not enable
  `->databaseTransactions()`, so a created record is committed before the
  `created` hook fires — the worker always finds the row. This correctness is
  currently implicit; a follow-up (see TODO) is to make it explicit with
  `->afterCommit()` (which needs test-config care, as a naive version breaks the
  dispatch test under `RefreshDatabase`'s wrapping transaction). The job also
  carries only the id and re-`find()`s with a null-guard, so an early run
  degrades to a safe no-op.
- **A queue worker must run** for either trigger to do anything (jobs use the
  `database` connection). Documented in `CLAUDE.md`. Locally: `php artisan queue:work`.

## Post-testing refinements (same PR)

Found while Ben test-drove the feature:

- **Auto-fetch was silent + no worker.** The first symptom ("no data for the new
  spot") was simply that no queue worker was running — the job sat in `jobs`.
  Added feedback so it's not a mystery: a **toast on spot-guide create**
  ("Weather fetch queued for X", via `CreateSpotGuide::afterCreate`) and a
  **dashboard status line** on `WeatherFetchWidget` (in-progress count from the
  `jobs` table + last-updated from `weather_records`).
- **Repeaters forced an empty required row.** Filament repeaters default to one
  item, so the create form demanded a windsurfing-location / where-to-stay /
  where-to-eat row (each with a required `name`) — a bare spot (a UK beach)
  couldn't save. Fixed with `->defaultItems(0)` on all three; a test asserts a
  valid spot saves with none.
- **`timezone` removed.** It was collected + stored + passed to the front end but
  never rendered (fetch uses `timezone=auto`), so the column and all references
  were dropped.
- **"View site" link** added to the admin top bar (opens the homepage in a new tab).

## Test plan

TDD throughout; all external I/O faked (`Http::fake`, `Sleep::fake`, `Queue::fake`,
notification DB assertions). Suite went 67 → **84 passing (475 assertions)**.
Coverage added: service upsert + idempotency + failure-isolation; auto-dispatch
on create-with-coords and no-dispatch/​no-op without coords (incl. deleted-model
guard); fetch-all writes all spots + notifies; widget button dispatches the job;
coordinates required + range-validated. Production build green.

## Security audit (same session — recorded, not yet fixed)

A pre-launch security audit ran this session. Findings are captured in
`docs/TODO.md` (dependency updates incl. Filament ≥3.3.53 for the RichEditor XSS
CVE; rate-limiting `/api/*` + `/contact`; `APP_DEBUG=false` in prod; the existing
single-admin section). To be addressed in the next branch, per Ben's request.

## Follow-ups

- `->afterCommit()` hardening on the auto-fetch dispatch (see `docs/TODO.md`).
- Browser render of the dashboard widget unverified (sits behind admin login);
  Livewire mount test passes and the theme compiles the widget's classes.
