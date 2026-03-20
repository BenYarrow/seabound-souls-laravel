import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import BlockWrapper from '../Common/BlockWrapper'

interface SingleImageProps {
    image: string
    backgroundColour?: string
}

const SingleImage = ({ image, backgroundColour }: SingleImageProps) => {
    const [toggler, setToggler] = useState(false)

    if (!image) return null

    return (
        <BlockWrapper options={{ fill: true, bgColourClass: backgroundColour }}>
            <button onClick={() => setToggler(!toggler)}>
                <img src={image} alt="" className="w-full rounded-lg" />
            </button>

            <FsLightbox
                toggler={toggler}
                sources={[image]}
                types={['image']}
            />
        </BlockWrapper>
    )
}

export default SingleImage
