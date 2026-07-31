---
title: Climate averages — exclude partial months
tags: [weather, destinations, data-quality, correctness]
status: stable
updated: 2026-07-31
completed: 2026-07-31
commits: [1e98fa6, ce1f6fa, 55688e3, 390de30, 85eae03, b0b4a82]
pr: 45
---

# Climate averages — exclude partial months

Ben reported that the `/destinations` wind and temperature curves looked wrong,
and that the data had "changed substantially" since the sailable-days work. It
had, and the charts were genuinely inflated.

## Root cause

`DestinationController` builds the "typical year" curve by grouping
`weather_records` by month and averaging across years with a plain `->avg()` —
**equal weight per year-row**. That is only valid if every row represents a
complete calendar month. Three things conspired so that it didn't:

1. **The fetch window started mid-month.** `now()->subYears(3)` lands on a day,
   not a month boundary, so the oldest month in range was a stub.
2. **Nothing pruned, and stubs froze.** `WeatherFetcher` only ever
   `updateOrCreate`d. Once the rolling window moved past a month it was never
   re-fetched, so a stub row was frozen permanently and kept voting.
3. **Every row weighed the same**, so 4 days of data counted as much as 31.

### Measured impact

Langebaan's July had **four** rows where every other month had three:

| July | Value | Days behind it |
|---|---|---|
| 2023 | **15.0 kts** | **4** (28–31 July) |
| 2024 | 12.6 kts | 31 |
| 2025 | 10.1 kts | 31 |
| 2026 | 8.5 kts | 28 |

Every spot had four July rows. Karpathos and Le Morne had **five** contaminated
months each — residue from an earlier fetch around March 2026 — and their 2023
rows for March–June had *zero* matching rows in `spot_sailable_days`, proving
they predated the daily layer and had never been refreshed since.

The error was biased, not random: those stub months happened to be windier than
their true monthly means, and being frozen they could never wash out.

## Why the ranking was right but the charts were wrong

This is the crux, and it explains why the bug survived the sailable-days work.
The two layers read different tables with **deliberately opposite rules**:

- **`spot_sailable_days`** (the ranking) is **coverage-normalised**
  (`qualifying ÷ held × daysInMonth`), so partial months are correct and useful
  there — including the current one.
- **`weather_records`** (the charts) is averaged with equal weight per row, so a
  partial month is poison.

The exact problem solved for one layer was never applied to the other. Ben's
instinct — "just average the dailies per month" — is already what a
`weather_records` row *is* within one year; the flaw was in the second
averaging, across years, which is invisible until you look for it.

Worth stating plainly because it will tempt a future change: **the daily table
cannot feed the charts.** It stores each day's *2nd-highest hour*, an order
statistic, not a daily mean — and no temperature at all.

## What shipped

**Window snapped to a month boundary** (`subYears(3)->startOfMonth()`), which
*gains* a month rather than discarding one — July 2023 becomes complete instead
of being dropped.

**A climate row is written only when the month is both fully received and fully
elapsed.** Completeness is decided by counting the days actually received
against the month's calendar length, not by comparing dates to the window.

**Rows are replaced per spot, not merged** — collected, then delete-and-insert
inside a transaction. This is what makes the fix **self-healing** and is why
there is no data migration: one re-fetch clears every frozen stub. The
collect-then-write ordering means a mid-fetch API failure throws before the
delete is reached, so a failed fetch can never blank a spot's charts.

**Days with no readings for a metric no longer contribute a phantom `0.0`** —
`$average` returns `0.0` for an empty array and that was being pushed
unconditionally. Fixed at both the daily→month rollup and, after the final
review, at the month level too.

### Result

Verified on the Postgres dev database after a full re-fetch: every spot now has
exactly **3 rows for every month** (36 rows = 36 complete months). Langebaan's
July 2023 row went from a 4-day stub at 15.0 kts to a full 31 days at 10.6, and
typical July moved 11.6 → 11.1 kts.

## Findings worth keeping

**Open-Meteo forecast-fills the current day.** A request at 09:31 on 2026-07-31
returned all 24 hours of that day. This was found only because a verification
step ran against real data and reported BLOCKED rather than forcing a pass — the
day-count rule alone accepted the current month on the last day of a month, on
forecast values. Hence the separate *elapsed* gate. Anything that reasons about
"complete" data from this API must not assume absence means not-yet-available.

**A completeness rule must be tested against received data, not the requested
range.** The archive lags real time by several days, so a month can sit inside
the window and still be missing its tail.

**Expect 2 rows, not 3, for the just-elapsed month during the first few days of a
new month** — the archive hasn't caught up. Every stored row is still a complete
month and equal weighting stays valid; this is only noted so nobody chases it.

## Test plan

- `php artisan test` — **263 passed** (1354 assertions); 6 new tests in
  `WeatherFetcherClimateMonthsTest`.
- Two pre-existing fixtures (`WeatherFetcherTest`, `FetchAllWeatherJobTest`) were
  widened from 2 days to a full month — required fallout of the completeness
  gate, with identical values so no assertion was weakened.
- Verified end-to-end on the real Postgres dev DB via `php artisan weather:fetch`
  across all 8 spots.

## Follow-ups

Recorded in [`docs/TODO.md`](../TODO.md): the `mph_*`/`kph_*` double-rounding
(a schema + frontend change, deliberately out of scope for a correctness fix),
a concurrency note now that rows are replaced rather than upserted, and the
archive-lag row-count note above.

**Production needs a `php artisan weather:fetch`** for any of this to take
effect there — the fix is self-healing but only on the next fetch.
