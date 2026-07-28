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

describe('sailableDaysInMonth (blend: gust >= min AND sustained >= 60% of min)', () => {
    it('steady spot: sustained wind is high enough that gust-qualifying days are unaffected', () => {
        // min 20 => floor = 12. Every day with gust >= 20 also has wind >= 12, so the
        // blend result equals the old gust-only result: proves steady spots unaffected.
        // gusts: 22,12,25,8,30,21 ; winds: 18,10,20,7,24,17
        // gust>=20 at i=0(22),2(25),4(30),5(21) => 4 candidates
        // wind floor 12: i=0 wind18>=12 ok, i=2 wind20>=12 ok, i=4 wind24>=12 ok, i=5 wind17>=12 ok => all 4 pass
        // 4 of 6 held days qualify. August (monthNumber 8) has 31 days: 4/6 * 31 = 20.6667.
        const month = { gusts: [22, 12, 25, 8, 30, 21], winds: [18, 10, 20, 7, 24, 17] }
        expect(sailableDaysInMonth(month, 20, 8)).toBeCloseTo(20.6667, 3)
    })

    it('excludes a gusty-but-not-steady day (storm spike) via the sustained floor', () => {
        // min 20 => floor = 12.
        // day0: gust 30 (>=20 ok), wind 8 (<12, FAILS floor) => excluded
        // day1: gust 25 (>=20 ok), wind 22 (>=12 ok) => included
        // 1 of 2 held days qualify. August has 31 days: 1/2 * 31 = 15.5.
        const month = { gusts: [30, 25], winds: [8, 22] }
        expect(sailableDaysInMonth(month, 20, 8)).toBeCloseTo(15.5, 5)
    })

    it('returns 0 for an undefined month or no held days', () => {
        expect(sailableDaysInMonth(undefined, 20, 8)).toBe(0)
        expect(sailableDaysInMonth({ gusts: [], winds: [] }, 20, 8)).toBe(0)
    })
})

describe('rankSpots', () => {
    // All August (monthNumber 8, 31 days) unless stated. Typical days = (qualifying / held) * 31.
    // min 20 => sustained floor = 12.
    const dataset: SailableDataset = {
        // Windy Aug: gusts [30,30,30,10], winds [20,20,20,20] — all winds clear the
        // 12 floor, so blend == gust-only: gust>=20 at i=0,1,2 => 3/4 * 31 = 23.25
        Windy: { 8: { gusts: [30, 30, 30, 10], winds: [20, 20, 20, 20] } },
        // Calm Aug: gusts [10,10] never clear 20 => 0/2 * 31 = 0
        Calm: { 8: { gusts: [10, 10], winds: [10, 10] } },
        // Mid Aug: gusts [25,10], winds [20,10] — gust>=20 at i=0, wind20>=12 ok => 1/2 * 31 = 15.5
        // Mid Jul: gusts [25,25,25], winds [20,20,20] — all qualify => 3/3 * 31 = 31
        Mid: { 8: { gusts: [25, 10], winds: [20, 10] }, 7: { gusts: [25, 25, 25], winds: [20, 20, 20] } },
    }

    it('ranks by typical sailable days in the selected month, descending', () => {
        const ranked = rankSpots(dataset, ['Windy', 'Calm', 'Mid'], 8, 20)
        expect(ranked.map((row) => row.title)).toEqual(['Windy', 'Mid', 'Calm'])
        expect(ranked[0].avgDaysThisMonth).toBeCloseTo(23.25, 5)  // Windy: 3/4 * 31
        expect(ranked[2].avgDaysThisMonth).toBe(0)                // Calm: 0/2 * 31
    })

    it('breaks ties by peak month then alphabetically', () => {
        const tie: SailableDataset = {
            // Aug: gust>=20 at i=0(25) w/ wind20>=12 ok => 1/2*31 = 15.5
            // Jul: gusts [25,10,10,10], winds [20,10,10,10] => gust>=20 only i=0, wind20>=12 ok => 1/4*31 = 7.75 => peak 15.5
            Bravo: { 8: { gusts: [25, 10], winds: [20, 10] }, 7: { gusts: [25, 10, 10, 10], winds: [20, 10, 10, 10] } },
            // Aug: same as Bravo => 15.5
            // Jul: gusts [25,25,25], winds [20,20,20] => all qualify => 3/3*31 = 31 => peak 31
            Alpha: { 8: { gusts: [25, 10], winds: [20, 10] }, 7: { gusts: [25, 25, 25], winds: [20, 20, 20] } },
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
