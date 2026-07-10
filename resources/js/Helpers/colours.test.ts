/**
 * Unit tests for the generated destination chart palette.
 * Uses a relative import (no @-alias) so the standalone Vitest config needs
 * no path resolver.
 */
import { describe, it, expect } from 'vitest'
import { getSpotGuideColours } from './colours'

/** Match `hsl(<number>, 48%, 48%)` — the fixed muted saturation/lightness. */
const HSL_MUTED = /^hsl\(\d+(\.\d+)?, 48%, 48%\)$/

describe('getSpotGuideColours', () => {
    it('returns one entry per title, keyed by title', () => {
        const titles = ['Tarifa', 'Karpathos', 'Dahab']
        const result = getSpotGuideColours(titles)
        expect(Object.keys(result)).toEqual(titles)
    })

    it('returns an empty object for no titles', () => {
        expect(getSpotGuideColours([])).toEqual({})
    })

    it('emits muted hsl(H, 48%, 48%) strings', () => {
        const result = getSpotGuideColours(['A', 'B', 'C'])
        Object.values(result).forEach((colour) => {
            expect(colour).toMatch(HSL_MUTED)
        })
    })

    it('assigns a distinct colour to each of 16 titles', () => {
        const titles = Array.from({ length: 16 }, (_, i) => `Spot ${i}`)
        const colours = Object.values(getSpotGuideColours(titles))
        expect(new Set(colours).size).toBe(16)
    })

    it('keeps colours distinct well beyond the old 16-colour ceiling (30 titles)', () => {
        const titles = Array.from({ length: 30 }, (_, i) => `Spot ${i}`)
        const colours = Object.values(getSpotGuideColours(titles))
        expect(new Set(colours).size).toBe(30)
    })

    it('is deterministic — same input yields identical output', () => {
        const titles = ['Tarifa', 'Karpathos', 'Dahab']
        expect(getSpotGuideColours(titles)).toEqual(getSpotGuideColours(titles))
    })

    it('maps a given index to the same hue regardless of list length', () => {
        // Golden-angle generation depends only on index, not total count.
        const short = getSpotGuideColours(['A', 'B'])
        const long = getSpotGuideColours(['A', 'B', 'C', 'D', 'E'])
        expect(short['A']).toBe(long['A'])
        expect(short['B']).toBe(long['B'])
    })
})
