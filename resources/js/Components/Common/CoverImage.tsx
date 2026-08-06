/**
 * CoverImage — the single renderer for every object-cover image. Applies the
 * image's focal point as CSS object-position so the subject stays in frame when
 * the image is cropped, and renders the photographer credit when there is one.
 * Tolerant of a plain URL string (focal defaults to centre, no credit) so
 * components can adopt it before the backend emits focal objects.
 *
 * The wrapper is rendered whether or not there is a credit, so layout can never
 * differ between a credited and an uncredited image. Callers' classNames size
 * and position the WRAPPER; the img always fills it.
 *
 * Position handling: the wrapper defaults to `relative` so it is always a valid
 * anchor for the absolutely-positioned credit badge. But several callers pass
 * `absolute inset-0 ...` themselves (to let the wrapper fill an already-`relative`
 * ancestor, often inside a flex layout where being pulled OUT of flow is load-
 * bearing — e.g. ContentWithBackgroundImage's flex row). Tailwind's generated
 * CSS always places `.relative` after `.absolute`/`.fixed`/`.sticky`, so with
 * equal specificity `.relative` wins the cascade regardless of class-list order
 * — a hardcoded `relative` would silently override a caller's `absolute` and
 * pull the image back into flow. So `relative` is only added when the caller
 * hasn't already specified a position utility of its own.
 */
import type { FocalImage } from '@/types/media'
import ImageCredit from '@/Components/Common/ImageCredit'

interface Props {
    image?: FocalImage | string | null
    alt?: string
    className?: string
    /**
     * Suppress the credit badge. Used where an image is UI chrome rather than
     * displayed photography — map pins/popups are small and a badge would be
     * illegible or would crowd the content there.
     */
    showCredit?: boolean
}

/** Tailwind position utilities; any of these already make an element a valid positioning context. */
const POSITION_UTILITIES = ['static', 'absolute', 'fixed', 'sticky', 'relative']

/**
 * Whether `className` already contains an explicit Tailwind position utility
 * (accounting for responsive/state variants like `lg:absolute`).
 *
 * @param className the caller-supplied class list to inspect
 */
const hasExplicitPosition = (className: string): boolean =>
    className
        .split(/\s+/)
        .some((token) => POSITION_UTILITIES.includes(token.split(':').pop() ?? ''))

const CoverImage = ({ image, alt, className = '', showCredit = true }: Props) => {
    if (!image) return null

    const isString = typeof image === 'string'
    const url = isString ? image : image.url
    if (!url) return null

    const focalX = isString ? 50 : image.focal_x ?? 50
    const focalY = isString ? 50 : image.focal_y ?? 50
    const resolvedAlt = alt ?? (isString ? '' : image.alt ?? '')
    const credit = !isString && showCredit ? image.credit : null

    const positionClass = hasExplicitPosition(className) ? '' : 'relative'
    const wrapperClassName = [positionClass, 'block overflow-hidden', className].filter(Boolean).join(' ')

    return (
        <span className={wrapperClassName}>
            <img
                src={url}
                alt={resolvedAlt}
                loading="lazy"
                className="object-cover w-full h-full"
                style={{ objectPosition: `${focalX}% ${focalY}%` }}
            />
            <ImageCredit credit={credit} />
        </span>
    )
}

export default CoverImage
