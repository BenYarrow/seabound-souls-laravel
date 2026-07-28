import { describe, it, expect } from 'vitest'
import { prepareClimateData, climateTempForMonth, type ClimateDataset } from '@/Helpers/climate'

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

describe('climateTempForMonth', () => {
    const dataset: ClimateDataset = {
        Tarifa: [
            { month: 'July', avgTemp: 26, ktsWind: 18, ktsGust: 22, mphWind: 21, mphGust: 25, kphWind: 33, kphGust: 41 },
            { month: 'August', avgTemp: 28, ktsWind: 21, ktsGust: 26, mphWind: 24, mphGust: 30, kphWind: 39, kphGust: 48 },
        ],
        Dahab: [
            { month: 'August', avgTemp: 33, ktsWind: 15, ktsGust: 19, mphWind: 17, mphGust: 22, kphWind: 28, kphGust: 35 },
        ],
    }

    it('returns the typical temp for a spot and month that are present', () => {
        expect(climateTempForMonth(dataset, 'Tarifa', 'August')).toBe(28)
    })

    it('returns null for a month absent from a spot that has other months', () => {
        expect(climateTempForMonth(dataset, 'Dahab', 'July')).toBeNull()
    })

    it('returns null for a spot absent from the dataset entirely', () => {
        expect(climateTempForMonth(dataset, 'Brouwersdam', 'August')).toBeNull()
    })
})
