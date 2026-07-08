import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import BlockWrapper from '../Common/BlockWrapper'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface ImagePairProps {
    /** Left and right image slots as focal-bearing objects (or legacy string URL / null). */
    imageLeft: FocalImage | string | null
    imageRight: FocalImage | string | null
    backgroundColour?: string
}

/** Extract the raw URL string needed by FsLightbox from a focal image or string. */
const toUrl = (img: FocalImage | string | null): string =>
    img ? (typeof img === 'string' ? img : img.url) : ''

const ImagePair = ({ imageLeft, imageRight, backgroundColour }: ImagePairProps) => {
    const [toggler, setToggler] = useState(false)
    const [currentImageIndex, setCurrentImageIndex] = useState(0)

    const images = [imageLeft, imageRight].filter(Boolean) as (FocalImage | string)[]

    if (images.length === 0) return null

    // FsLightbox needs raw URL strings.
    const lightboxSources = images.map(toUrl)

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
