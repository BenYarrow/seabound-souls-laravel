import { describe, expect, it } from 'vitest'
import { resolveCredit } from '@/Helpers/imageCredit'

describe('resolveCredit', () => {
    it('returns none when there is no credit', () => {
        expect(resolveCredit(null).kind).toBe('none')
        expect(resolveCredit(undefined).kind).toBe('none')
    })

    it('returns text when the photographer has no link', () => {
        const result = resolveCredit({ name: 'Hamish', url: null })

        expect(result.kind).toBe('text')
        expect(result.name).toBe('Hamish')
        expect(result.href).toBeNull()
    })

    it('treats an absolute URL as external', () => {
        const result = resolveCredit({ name: 'Hamish', url: 'https://instagram.com/hamish' })

        expect(result.kind).toBe('external')
        expect(result.href).toBe('https://instagram.com/hamish')
        expect(result.label).toBe('Photo by Hamish, opens in a new tab')
    })

    it('treats a relative URL as an internal profile link', () => {
        const result = resolveCredit({ name: 'Hamish', url: '/photographers/hamish' })

        expect(result.kind).toBe('internal')
        expect(result.href).toBe('/photographers/hamish')
        expect(result.label).toBe('Photo by Hamish')
    })

    it('falls back to text for a blank name', () => {
        expect(resolveCredit({ name: '', url: 'https://example.com' }).kind).toBe('none')
    })

    it('treats a protocol-relative URL as external, not an internal path', () => {
        // `//example.com/x` starts with a single `/` but is not a same-site path —
        // browsers resolve it against the current protocol to a different origin.
        // Routing it through Inertia's client router (the "internal" branch) would
        // navigate to a non-existent local route like /example.com/x.
        const result = resolveCredit({ name: 'Hamish', url: '//example.com/hamish' })

        expect(result.kind).toBe('external')
        expect(result.href).toBe('//example.com/hamish')
        expect(result.label).toBe('Photo by Hamish, opens in a new tab')
    })
})
