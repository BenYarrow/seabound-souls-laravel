// resources/js/Helpers/sailableDays.ts
//
// Client-side sailable-days ranking for the destinations page. A day is
// "sailable" at a minimum X (kts) when BOTH: its stored 2nd-highest sailing-
// window GUST >= X, AND its 2nd-highest sailing-window SUSTAINED wind clears a
// floor of 60% of X. The sustained floor filters out gusty-but-not-steady days
// (storm spikes with a high gust over a low steady baseline) that pure-gust
// ranking let through — steady spots, whose sustained wind already tracks their
// gust, are unaffected. Per month we hold every such daily (gust, wind) pair
// pooled across the years we have. Because the fetch window rolls (so boundary
// years are only partially covered — and the current month is always a
// boundary), we do NOT divide by a year count: that would undercount partial
// months by nearly half. Instead the typical sailable-day count is a
// coverage-normalised rate — the share of held days that qualify under the
// blend, scaled to the month's calendar length:
//   (qualifying under blend).length / gusts.length * daysInMonth.
// Spots are then ranked by the selected month's typical count.

export type WindUnit = 'kts' | 'mph' | 'kph'

/**
 * One spot-month: every held day's qualifying gust and sustained wind (kts),
 * pooled across all held years. `gusts[i]` and `winds[i]` are the SAME day's
 * 2nd-highest sailing-window gust and sustained hour — the two arrays are
 * index-aligned and guaranteed equal length.
 */
export interface SailableMonth {
    gusts: number[]
    winds: number[]
}

/**
 * The sustained-wind floor, as a fraction of the chosen minimum gust. A day
 * only counts as sailable when its sustained wind is at least this fraction of
 * the minimum, in addition to clearing the gust minimum outright. This filters
 * gusty-but-not-steady days (storm spikes) without penalising steady spots,
 * whose sustained wind already sits close to their gust.
 */
export const SUSTAINED_FLOOR_FRACTION = 0.6

/** Calendar days per month, index 0 = January. February fixed at 28 — the sub-day
 *  leap-year error is immaterial to a climatological estimate. */
const DAYS_IN_MONTH = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31]

/** title -> month (1-12) -> pooled daily data. */
export type SailableDataset = Record<string, Record<number, SailableMonth>>

/** Multipliers from knots to each display unit. */
const KTS_TO: Record<WindUnit, number> = { kts: 1, mph: 1.15078, kph: 1.852 }

/** Selectable minimum-wind options per unit, in steps of 5 (roughly equivalent ranges). */
export const MIN_OPTIONS: Record<WindUnit, number[]> = {
    kts: [5, 10, 15, 20, 25, 30, 35, 40, 45, 50],
    mph: [5, 10, 15, 20, 25, 30, 35, 40, 45, 50, 55],
    kph: [10, 15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 65, 70, 75, 80, 85, 90, 95],
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
 * Typical (coverage-normalised) number of sailable days in a month for the given
 * minimum gust (kts): the share of pooled held days that clear the BLEND —
 * gust >= minKts AND sustained wind >= SUSTAINED_FLOOR_FRACTION * minKts —
 * scaled to the month's calendar length. `monthNumber` is 1-12 and selects that
 * length. Robust to partial boundary months because it normalises by held days,
 * not years. `gusts` and `winds` are index-aligned (same day, per position).
 */
export const sailableDaysInMonth = (
    month: SailableMonth | undefined,
    minKts: number,
    monthNumber: number
): number => {
    if (!month || month.gusts.length === 0) {
        return 0
    }
    const floor = minKts * SUSTAINED_FLOOR_FRACTION
    const qualifyingCount = month.gusts.filter(
        (gust, index) => gust >= minKts && month.winds[index] >= floor
    ).length
    const daysInMonth = DAYS_IN_MONTH[monthNumber - 1]
    return (qualifyingCount / month.gusts.length) * daysInMonth
}

/** A spot ranked for a selected month: this month's typical count plus all 12 months.
 *  `avgDaysThisMonth` is the coverage-normalised typical count (name kept for
 *  downstream consumers), not a literal average across years. */
export interface RankedSpot {
    title: string
    avgDaysThisMonth: number
    daysPerMonth: number[]
}

/**
 * Rank spots by typical sailable days in `month` (1-12) at `minKts`, descending.
 * Ties break by the spot's single best month, then alphabetically by title, so
 * the order is deterministic and shareable. Spots with no qualifying days — and
 * spots with no dataset entry at all — remain in the list (ranked 0, at the
 * bottom), so a dataless spot never disappears from the page.
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
            sailableDaysInMonth(spotMonths[index + 1], minKts, index + 1)
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
