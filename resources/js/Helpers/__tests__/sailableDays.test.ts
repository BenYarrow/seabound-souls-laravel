import { describe, it, expect } from 'vitest'
// Relative import (no @-alias) — the standalone Vitest config (see
// vitest.config.ts) does not merge vite.config.ts's alias resolver, so
// `@/Helpers/...` does not resolve here even though it does in the app build.
// Matches the convention already established in colours.test.ts.
import {
    unitToKts, ktsToUnit, snapToUnitOption, sailableDaysInMonth, rankSpots,
    type SailableDataset,
} from '../sailableDays'

describe('unit conversion', () => {
    it('round-trips kts through mph', () => {
        expect(ktsToUnit(20, 'kts')).toBe(20)
        expect(unitToKts(ktsToUnit(20, 'mph'), 'mph')).toBeCloseTo(20, 5)
    })
    it('snaps a kts value to the nearest option in the target unit', () => {
        // 20 kts * 1.15078 = 23.0156 mph -> nearest 5-step mph option (5..55) is 25 (|25-23.02|=1.98 < |20-23.02|=3.02)
        expect(snapToUnitOption(20, 'mph')).toBe(25)
        // 20 kts * 1.852 = 37.04 kph -> nearest 5-step kph option (10..95) is 35 (|35-37.04|=2.04 < |40-37.04|=2.96)
        expect(snapToUnitOption(20, 'kph')).toBe(35)
        expect(snapToUnitOption(20, 'kts')).toBe(20)
    })
})

describe('sailableDaysInMonth', () => {
    it('scales the qualifying share to the length of the month', () => {
        // >= 20kts: 22,25,30,21 => 4 of 6 held days qualify.
        // August (monthNumber 8) has 31 days: 4/6 * 31 = 20.6667 typical sailable days.
        const month = { values: [22, 12, 25, 8, 30, 21] }
        expect(sailableDaysInMonth(month, 20, 8)).toBeCloseTo(20.6667, 3)
    })
    it('returns 0 for an undefined month or no held days', () => {
        expect(sailableDaysInMonth(undefined, 20, 8)).toBe(0)
        expect(sailableDaysInMonth({ values: [] }, 20, 8)).toBe(0)
    })
})

describe('rankSpots', () => {
    // All August (monthNumber 8, 31 days) unless stated. Typical days = (qualifying / held) * 31.
    const dataset: SailableDataset = {
        Windy: { 8: { values: [30, 30, 30, 10] } },  // Aug: 3/4 * 31 = 23.25
        Calm: { 8: { values: [10, 10] } },           // Aug: 0/2 * 31 = 0
        Mid: { 8: { values: [25, 10] }, 7: { values: [25, 25, 25] } }, // Aug: 1/2 * 31 = 15.5; Jul: 3/3 * 31 = 31
    }

    it('ranks by typical sailable days in the selected month, descending', () => {
        const ranked = rankSpots(dataset, ['Windy', 'Calm', 'Mid'], 8, 20)
        expect(ranked.map((row) => row.title)).toEqual(['Windy', 'Mid', 'Calm'])
        expect(ranked[0].avgDaysThisMonth).toBeCloseTo(23.25, 5)  // Windy: 3/4 * 31
        expect(ranked[2].avgDaysThisMonth).toBe(0)                // Calm: 0/2 * 31
    })

    it('breaks ties by peak month then alphabetically', () => {
        const tie: SailableDataset = {
            // Aug 1/2*31 = 15.5; Jul 1/4*31 = 7.75 => peak 15.5
            Bravo: { 8: { values: [25, 10] }, 7: { values: [25, 10, 10, 10] } },
            // Aug 1/2*31 = 15.5; Jul 3/3*31 = 31   => peak 31
            Alpha: { 8: { values: [25, 10] }, 7: { values: [25, 25, 25] } },
        }
        const ranked = rankSpots(tie, ['Bravo', 'Alpha'], 8, 20)
        // Equal August count (15.5); Alpha has the higher peak month (31 vs 15.5) so it leads.
        expect(ranked.map((row) => row.title)).toEqual(['Alpha', 'Bravo'])
    })

    it('fills daysPerMonth with 12 entries indexed from January', () => {
        const ranked = rankSpots(dataset, ['Mid'], 8, 20)
        expect(ranked[0].daysPerMonth).toHaveLength(12)
        expect(ranked[0].daysPerMonth[7]).toBeCloseTo(15.5, 5) // August: 1/2 * 31
        expect(ranked[0].daysPerMonth[6]).toBeCloseTo(31, 5)   // July:   3/3 * 31
        expect(ranked[0].daysPerMonth[0]).toBe(0)              // January (no data)
    })
})
