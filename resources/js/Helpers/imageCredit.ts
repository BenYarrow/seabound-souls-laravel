/**
 * imageCredit — decides how a photographer credit should be rendered.
 *
 * Kept as a pure function (rather than logic inside ImageCredit.tsx) so it is
 * testable under this project's node-environment Vitest setup, which has no DOM
 * or React testing library. The component is a thin switch over the result.
 */

/** A photographer credit as emitted by MediaLibrary::imagePayload(). */
export interface ImageCreditData {
    name: string
    url: string | null
}

/** How the credit should render: nothing, plain text, or one of two link kinds. */
export type CreditKind = 'none' | 'text' | 'external' | 'internal'

export interface ResolvedCredit {
    kind: CreditKind
    name: string
    href: string | null
    /** Accessible label for the anchor, or the plain name when unlinked. */
    label: string
}

/**
 * Resolve a raw credit into everything the badge needs to render.
 *
 * A relative URL means the photographer's own page on this site and gets an
 * Inertia link; an absolute URL is somebody else's site and opens in a new tab.
 * Anything unusable degrades to `none` so a dead link is never rendered.
 *
 * @param credit the credit from the image payload, if any
 */
export const resolveCredit = (credit?: ImageCreditData | null): ResolvedCredit => {
    const name = credit?.name?.trim() ?? ''

    if (!name) {
        return { kind: 'none', name: '', href: null, label: '' }
    }

    const href = credit?.url?.trim() || null

    if (!href) {
        return { kind: 'text', name, href: null, label: `Photo by ${name}` }
    }

    const isInternal = href.startsWith('/')

    return {
        kind: isInternal ? 'internal' : 'external',
        name,
        href,
        label: isInternal ? `Photo by ${name}` : `Photo by ${name}, opens in a new tab`,
    }
}
