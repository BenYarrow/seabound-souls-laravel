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
 * Full month names in calendar order, matching the `month` string the backend
 * emits on each `ClimateMonth` (see `DestinationController`'s `$monthNames` map).
 * Used to derive a display label from a 1-12 `selectedMonth` index for chart
 * reference lines, without re-deriving the mapping in every chart component.
 */
export const MONTH_NAMES = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
]

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
