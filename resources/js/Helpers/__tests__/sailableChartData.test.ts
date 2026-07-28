// resources/js/Helpers/__tests__/sailableChartData.test.ts
//
// Unit tests for the sailable-days chart data helper: verifies the pivot from
// ranked-spot rows (one row per spot, 12 months each) into Recharts rows (one
// row per month, one numeric key per spot title).

import { describe, it, expect } from 'vitest'
import { prepareSailableChartData, MONTH_LABELS } from '@/Helpers/sailableChartData'
import type { RankedSpot } from '@/Helpers/sailableDays'

describe('prepareSailableChartData', () => {
    it('produces one row per month with a key per spot', () => {
        const ranked: RankedSpot[] = [
            { title: 'Windy', avgDaysThisMonth: 4, daysPerMonth: [0, 0, 0, 0, 0, 0, 0, 4, 0, 0, 0, 0] },
            { title: 'Mid', avgDaysThisMonth: 2, daysPerMonth: [0, 0, 0, 0, 0, 0, 3, 2, 0, 0, 0, 0] },
        ]
        const rows = prepareSailableChartData(ranked)
        expect(rows).toHaveLength(12)
        expect(MONTH_LABELS).toHaveLength(12)
        expect(rows[7]).toEqual({ month: 'Aug', Windy: 4, Mid: 2 })
        expect(rows[6]).toEqual({ month: 'Jul', Windy: 0, Mid: 3 })
    })
})
