/**
 * ImagePair — a content-builder block rendering two images side by side, each
 * cropped to a square via CoverImage. Clicking either opens a shared lightbox
 * scrolled to the clicked image.
 */
import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import BlockWrapper from '../Common/BlockWrapper'
import CoverImage from '@/Components/Common/CoverImage'
import CreditedLightboxSlide from '@/Components/Common/CreditedLightboxSlide'
import type { FocalImage } from '@/types/media'

interface ImagePairProps {
    /** Left and right image slots as focal-bearing objects (or legacy string URL / null). */
    imageLeft: FocalImage | string | null
    imageRight: FocalImage | string | null
    backgroundColour?: string
}

/**
 * Render up to two images side by side in a two-column grid, each opening a
 * shared lightbox (with photographer credit, when present) on click.
 *
 * @param imageLeft the left-hand image, or null to omit that slot
 * @param imageRight the right-hand image, or null to omit that slot
 * @param backgroundColour section background utility class, forwarded to BlockWrapper
 */
const ImagePair = ({ imageLeft, imageRight, backgroundColour }: ImagePairProps) => {
    const [toggler, setToggler] = useState(false)
    const [currentImageIndex, setCurrentImageIndex] = useState(0)

    const images = [imageLeft, imageRight].filter(Boolean) as (FocalImage | string)[]

    if (images.length === 0) return null

    // A string image has no credit to lose; a focal object with a credit
    // becomes a JSX custom source (see Gallery.tsx for the same pattern) so
    // the lightbox shows the photographer's attribution, not just the URL.
    const lightboxSources = images.map((img) =>
        typeof img !== 'string' && img.credit ? (
            <CreditedLightboxSlide url={img.url} alt={img.alt ?? ''} credit={img.credit} />
        ) : (
            typeof img === 'string' ? img : img.url
        )
    )

    return (
        <BlockWrapper options={{ fill: true, bgColourClass: backgroundColour }}>
            <div className="grid grid-cols-2">
                {images.map((img, index) => (
                    <button
                        key={index}
                        onClick={() => {
                            setCurrentImageIndex(index)
                            setToggler(!toggler)
                        }}
                        className="block w-full h-full aspect-square"
                    >
                        <CoverImage image={img} className="w-full h-full" />
                    </button>
                ))}
            </div>

            <FsLightbox
                toggler={toggler}
                sources={lightboxSources}
                sourceIndex={currentImageIndex}
                types={['image', 'image']}
            />
        </BlockWrapper>
    )
}

export default ImagePair
