import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import BlockWrapper from '../Common/BlockWrapper'
import AnimateInView from '../Common/AnimateInView'

interface SplitImageTextProps {
    image: string
    text: string
    reverse: boolean
}

const SplitImageText = ({ image, text, reverse }: SplitImageTextProps) => {
    const [toggler, setToggler] = useState(false)

    return (
        <BlockWrapper>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-8 lg:gap-12">
                {image && (
                    <AnimateInView
                        tag="div"
                        outViewClasses={`${reverse ? 'translate-x-20' : '-translate-x-20'} opacity-0`}
                        inViewClasses="translate-x-0 opacity-100"
                        classes={reverse ? 'order-last' : 'order-first'}
                    >
                        <button onClick={() => setToggler(!toggler)} className="block w-full h-full">
                            <img src={image} alt="" className="w-full rounded-lg" />
                        </button>
                    </AnimateInView>
                )}

                {text && (
                    <AnimateInView
                        tag="div"
                        outViewClasses={`${reverse ? '-translate-x-20' : 'translate-x-20'} opacity-0`}
                        inViewClasses="translate-x-0 opacity-100"
                        classes="prose prose-lg max-w-none"
                    >
                        <div dangerouslySetInnerHTML={{ __html: text }} />
                    </AnimateInView>
                )}
            </div>

            {image && (
                <FsLightbox
                    toggler={toggler}
                    sources={[image]}
                    types={['image']}
                />
            )}
        </BlockWrapper>
    )
}

export default SplitImageText
