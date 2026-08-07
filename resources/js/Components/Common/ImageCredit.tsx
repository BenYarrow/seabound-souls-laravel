/**
 * ImageCredit — the small photographer attribution badge shown over an image.
 *
 * Always visible rather than hover-only: hover reveals nothing on touch devices,
 * which is where most of the site is read. Sits over arbitrary photography, so it
 * carries its own scrim and reads the same in light and dark — the photo is its
 * background, not the page.
 */
import { Link } from '@inertiajs/react'
import { resolveCredit, type ImageCreditData } from '@/Helpers/imageCredit'
import type { MouseEvent } from 'react'

interface Props {
    credit?: ImageCreditData | null
}

/**
 * Shared visual treatment for all three rendered forms.
 *
 * `focus-visible:ring-inset` matters here: the badge sits flush at
 * `bottom-0 right-0` inside a wrapper with `overflow-hidden` (see
 * CoverImage), so an outset default focus outline gets clipped by the
 * ancestor and is invisible when tabbing to the link. Inset keeps the ring
 * inside the badge's own box.
 */
const BADGE_CLASSES =
    'absolute bottom-0 right-0 z-10 px-2 py-1 text-[10px] leading-none tracking-wide ' +
    'text-white/90 bg-secondary/70 backdrop-blur-sm rounded-tl pointer-events-auto ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-lighter'

/**
 * Render the credit badge appropriate to the resolved credit kind, or nothing
 * at all when there is no usable credit.
 *
 * @param credit the raw credit from the image payload, if any
 */
/**
 * Stop the badge's click from bubbling to an ancestor Link/button.
 *
 * `CoverImage` (and therefore this badge) is routinely rendered inside an
 * Inertia `<Link>` (destination/blog cards) or a `<button onClick>` (gallery
 * lightbox triggers). A click on the credit badge is still a click inside
 * that ancestor, so without stopping propagation the ancestor's own handler
 * fires too: an outer `Link` intercepts the click and navigates to the
 * *card's* destination instead of the photographer's page, and an outer
 * button's `onClick` (e.g. opening the lightbox) fires alongside whatever
 * the badge itself does. `stopPropagation` only blocks bubbling to
 * ancestors — it does not call `preventDefault`, so it does not interfere
 * with this element's own default action (following the href) or, for the
 * internal case below, with Inertia's own interception of its own `Link`
 * (which runs after this handler and only checks `event.defaultPrevented`,
 * not propagation).
 */
const stopBubblingToAncestor = (event: MouseEvent) => {
    event.stopPropagation()
}

const ImageCredit = ({ credit }: Props) => {
    const resolved = resolveCredit(credit)

    if (resolved.kind === 'none') return null

    if (resolved.kind === 'text') {
        // Plain text, not a link — there is no navigation of its own to protect,
        // so propagation is deliberately left alone. Stopping it here would turn
        // the badge into a dead zone inside an otherwise-clickable card: a click
        // that lands on the credit text would silently fail to follow the card's
        // own Link/button instead of just doing nothing extra.
        return <span className={BADGE_CLASSES}>{`© ${resolved.name}`}</span>
    }

    if (resolved.kind === 'internal') {
        return (
            <Link
                href={resolved.href as string}
                aria-label={resolved.label}
                onClick={stopBubblingToAncestor}
                className={`${BADGE_CLASSES} hover:bg-secondary/90 transition-colors duration-200`}
            >
                {`© ${resolved.name}`}
            </Link>
        )
    }

    return (
        <a
            href={resolved.href as string}
            target="_blank"
            rel="noopener noreferrer"
            aria-label={resolved.label}
            onClick={stopBubblingToAncestor}
            className={`${BADGE_CLASSES} hover:bg-secondary/90 transition-colors duration-200`}
        >
            {`© ${resolved.name}`}
        </a>
    )
}

export default ImageCredit
