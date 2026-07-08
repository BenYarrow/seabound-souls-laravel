import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import BlockWrapper from '../Common/BlockWrapper'
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

    return (
        <BlockWrapper options={{ fill: true, bgColourClass: backgroundColour }}>
            <button onClick={() => setToggler(!toggler)}>
                <img src={url} alt={alt} className="w-full rounded-lg" />
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
