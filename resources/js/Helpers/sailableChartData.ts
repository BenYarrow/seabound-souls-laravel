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
