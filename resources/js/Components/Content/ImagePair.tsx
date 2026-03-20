import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import BlockWrapper from '../Common/BlockWrapper'

interface ImagePairProps {
    imageLeft: string
    imageRight: string
    backgroundColour?: string
}

const ImagePair = ({ imageLeft, imageRight, backgroundColour }: ImagePairProps) => {
    const [toggler, setToggler] = useState(false)
    const [currentImageIndex, setCurrentImageIndex] = useState(0)

    const images = [imageLeft, imageRight].filter(Boolean)

    if (images.length === 0) return null

    return (
        <BlockWrapper options={{ fill: true, bgColourClass: backgroundColour }}>
            <div className="grid grid-cols-2">
                {images.map((src, index) => (
                    <button
                        key={index}
                        onClick={() => {
                            setCurrentImageIndex(index)
                            setToggler(!toggler)
                        }}
                        className="block w-full h-full aspect-square"
                    >
                        <img src={src} alt="" className="w-full h-full object-cover" />
                    </button>
                ))}
            </div>

            <FsLightbox
                toggler={toggler}
                sources={images}
                sourceIndex={currentImageIndex}
                types={['image', 'image']}
            />
        </BlockWrapper>
    )
}

export default ImagePair
