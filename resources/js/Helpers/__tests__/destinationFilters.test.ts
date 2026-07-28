import { describe, it, expect } from 'vitest'
import { parseFilters, filtersToQuery, type DestinationFilters } from '@/Helpers/destinationFilters'

describe('destination filters URL sync', () => {
    it('falls back to defaults on an empty query', () => {
        const filters = parseFilters('', { month: 7 })
        expect(filters).toEqual({ month: 7, min: 20, unit: 'kts', group: 'continent', spots: [] })
    })

    it('parses a full query string', () => {
        const filters = parseFilters('?month=8&min=25&unit=mph&group=global&spots=vassiliki,tarifa', { month: 7 })
        expect(filters).toEqual({ month: 8, min: 25, unit: 'mph', group: 'global', spots: ['vassiliki', 'tarifa'] })
    })

    it('round-trips filters through query and back', () => {
        const original: DestinationFilters = { month: 3, min: 30, unit: 'kph', group: 'country', spots: ['dahab'] }
        const restored = parseFilters('?' + new URLSearchParams(filtersToQuery(original)).toString(), { month: 7 })
        expect(restored).toEqual(original)
    })

    it('ignores invalid values and clamps to defaults', () => {
        const filters = parseFilters('?month=99&unit=furlongs&group=nonsense', { month: 7 })
        expect(filters.month).toBe(7)
        expect(filters.unit).toBe('kts')
        expect(filters.group).toBe('continent')
    })

    it('rejects an off-grid min for the resolved unit, falling back to the default', () => {
        expect(parseFilters('?min=17&unit=kts', { month: 7 }).min).toBe(20)
        expect(parseFilters('?min=999&unit=mph', { month: 7 }).min).toBe(20)
    })

    it('still accepts a valid on-grid, non-default min', () => {
        expect(parseFilters('?min=35&unit=kts', { month: 7 }).min).toBe(35)
    })
})
