/**
 * CoverImage — the single renderer for every object-cover image. Applies the
 * image's focal point as CSS object-position so the subject stays in frame when
 * the image is cropped. Tolerant of a plain URL string (focal defaults to
 * centre) so components can adopt it before the backend emits focal objects.
 */
import type { FocalImage } from '@/types/media'

interface Props {
    image?: FocalImage | string | null
    alt?: string
    className?: string
}

const CoverImage = ({ image, alt, className = '' }: Props) => {
    if (!image) return null

    const isString = typeof image === 'string'
    const url = isString ? image : image.url
    if (!url) return null

    const focalX = isString ? 50 : image.focal_x ?? 50
    const focalY = isString ? 50 : image.focal_y ?? 50
    const resolvedAlt = alt ?? (isString ? '' : image.alt ?? '')

    return (
        <img
            src={url}
            alt={resolvedAlt}
            loading="lazy"
            className={`object-cover ${className}`}
            style={{ objectPosition: `${focalX}% ${focalY}%` }}
        />
    )
}

export default CoverImage
