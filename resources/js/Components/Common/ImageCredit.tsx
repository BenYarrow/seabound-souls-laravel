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

interface Props {
    credit?: ImageCreditData | null
}

/** Shared visual treatment for all three rendered forms. */
const BADGE_CLASSES =
    'absolute bottom-0 right-0 z-10 px-2 py-1 text-[10px] leading-none tracking-wide ' +
    'text-white/90 bg-secondary/70 backdrop-blur-sm rounded-tl pointer-events-auto'

/**
 * Render the credit badge appropriate to the resolved credit kind, or nothing
 * at all when there is no usable credit.
 *
 * @param credit the raw credit from the image payload, if any
 */
const ImageCredit = ({ credit }: Props) => {
    const resolved = resolveCredit(credit)

    if (resolved.kind === 'none') return null

    if (resolved.kind === 'text') {
        return <span className={BADGE_CLASSES}>{`© ${resolved.name}`}</span>
    }

    if (resolved.kind === 'internal') {
        return (
            <Link
                href={resolved.href as string}
                aria-label={resolved.label}
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
            className={`${BADGE_CLASSES} hover:bg-secondary/90 transition-colors duration-200`}
        >
            {`© ${resolved.name}`}
        </a>
    )
}

export default ImageCredit
