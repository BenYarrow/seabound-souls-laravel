import { flatMap, forEach, uniq } from 'lodash'

export type WeatherDataset = Record<string, Record<number, WeatherMonth[]>>

export interface WeatherMonth {
    month: string
    avgTemp: number
    ktsWind: number
    ktsGust: number
    mphWind: number
    mphGust: number
    kphWind: number
    kphGust: number
}

export const prepareYearlyWindData = (
    dataset: WeatherDataset,
    year: number,
    datapoint: string
): Record<string, any>[] => {
    const months = uniq(
        flatMap(dataset, (years) => (years[year] || []).map((entry) => entry.month))
    )

    return months.map((month) => {
        const monthEntry: Record<string, any> = { month }

        forEach(dataset, (years, location) => {
            const monthData = (years[year] || []).find((entry) => entry.month === month)
            if (monthData) {
                monthEntry[location] = monthData[datapoint as keyof WeatherMonth]
            }
        })

        return monthEntry
    })
}

export const prepareYearlyTempData = (
    dataset: WeatherDataset,
    year: number
): Record<string, any>[] => {
    const months = uniq(
        flatMap(dataset, (years) => (years[year] || []).map((entry) => entry.month))
    )

    return months.map((month) => {
        const monthEntry: Record<string, any> = { month }

        forEach(dataset, (years, location) => {
            const monthData = (years[year] || []).find((entry) => entry.month === month)
            if (monthData) {
                monthEntry[location] = monthData.avgTemp
            }
        })

        return monthEntry
    })
}
