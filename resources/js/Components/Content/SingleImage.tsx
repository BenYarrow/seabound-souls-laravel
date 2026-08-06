/**
 * SingleImage — a content-builder block rendering one image full-width at its
 * NATURAL aspect ratio (no cropping). Clicking it opens the image in a
 * lightbox.
 */
import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import BlockWrapper from '../Common/BlockWrapper'
import ImageCredit from '@/Components/Common/ImageCredit'
import type { FocalImage } from '@/types/media'

interface SingleImageProps {
    /** Focal-bearing image object (or legacy string URL / null when no image). */
    image: FocalImage | string | null
    backgroundColour?: string
}

const SingleImage = ({ image, backgroundColour }: SingleImageProps) => {
    const [toggler, setToggler] = useState(false)

    if (!image) return null

    // Extract the raw URL for non-CoverImage consumers (img src, FsLightbox sources).
    const url = typeof image === 'string' ? image : image.url
    const alt = typeof image === 'string' ? '' : (image.alt ?? '')
    // A legacy string image predates credit support, so there is nothing to resolve.
    const credit = typeof image === 'string' ? null : (image.credit ?? null)

    return (
        <BlockWrapper options={{ fill: true, bgColourClass: backgroundColour }}>
            {/*
             * Deliberately NOT routed through CoverImage. CoverImage forces
             * object-cover into a caller-sized wrapper, which is right for
             * cropped tiles/hero images but wrong here: SingleImage's whole
             * point is a full-width image at its own natural aspect ratio
             * (height auto). A CoverImage wrapper has no intrinsic height of
             * its own, so without an explicit height the image would collapse
             * to nothing — or, given one, would get cropped instead of shown
             * whole. Either is a visible regression, not a refactor. So the
             * plain <img> stays; only the surrounding <button> gains explicit
             * `relative block w-full` so it is a correctly sized positioning
             * context for the absolutely-positioned ImageCredit badge, which
             * would otherwise anchor to the wrong ancestor (an unstyled
             * <button> is inline-block and shrinks to content).
             */}
            <button
                onClick={() => setToggler(!toggler)}
                className="relative block w-full"
            >
                <img src={url} alt={alt} className="w-full rounded-lg" />
                <ImageCredit credit={credit} />
            </button>

            <FsLightbox
                toggler={toggler}
                sources={[url]}
                types={['image']}
            />
        </BlockWrapper>
    )
}

export default SingleImage
