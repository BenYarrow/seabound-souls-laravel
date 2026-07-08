import { useState } from 'react'
import FsLightbox from 'fslightbox-react'
import AnimateInView from '../Common/AnimateInView'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface SplitImageTextProps {
    /** Focal-bearing image object (or legacy string URL / null when no image). */
    image: FocalImage | string | null
    text: string
    reverse: boolean
}

const SplitImageText = ({ image, text, reverse }: SplitImageTextProps) => {
    const [toggler, setToggler] = useState(false)

    return (
        <section className="bg-cream overflow-hidden">
            <div className="container mx-auto py-16 lg:py-24">
                <div className={`flex flex-col ${reverse ? 'lg:flex-row-reverse' : 'lg:flex-row'} gap-10 lg:gap-16 xl:gap-24 items-center`}>

                    {/* ── Image column ── */}
                    {image && (
                        <AnimateInView
                            tag="div"
                            outViewClasses={`${reverse ? 'translate-x-16' : '-translate-x-16'} opacity-0`}
                            inViewClasses="translate-x-0 opacity-100"
                            durationClasses="duration-700"
                            classes="w-full lg:w-[45%] xl:w-[42%] shrink-0"
                        >
                            <button
                                onClick={() => setToggler(!toggler)}
                                className="block w-full group"
                            >
                                <div className="relative overflow-hidden aspect-[4/5] shadow-[0_20px_60px_-10px_rgba(0,0,0,0.28)]">
                                    <CoverImage
                                        image={image}
                                        alt=""
                                        className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-700 ease-out"
                                    />
                                    {/* Orange corner brackets — opposite corners */}
                                    <div className={`absolute top-0 ${reverse ? 'right-0 border-r-2' : 'left-0 border-l-2'} w-10 h-10 border-t-2 border-orange pointer-events-none`} />
                                    <div className={`absolute bottom-0 ${reverse ? 'left-0 border-l-2' : 'right-0 border-r-2'} w-10 h-10 border-b-2 border-orange pointer-events-none`} />
                                    {/* Subtle hover darkening */}
                                    <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-500 pointer-events-none" />
                                </div>
                            </button>
                        </AnimateInView>
                    )}

                    {/* ── Text column ── */}
                    {text && (
                        <AnimateInView
                            tag="div"
                            outViewClasses={`${reverse ? '-translate-x-16' : 'translate-x-16'} opacity-0`}
                            inViewClasses="translate-x-0 opacity-100"
                            durationClasses="duration-700"
                            delayClasses="delay-150"
                            classes="flex-1 min-w-0"
                        >
                            <div className="w-8 h-0.5 bg-orange mb-6" />
                            <div
                                className="prose prose-lg max-w-none prose-headings:font-display prose-headings:tracking-wide prose-headings:text-secondary prose-a:text-primary [&>*:last-child]:!mb-0"
                                dangerouslySetInnerHTML={{ __html: text }}
                            />
                        </AnimateInView>
                    )}

                </div>
            </div>

            {image && (
                // FsLightbox needs a raw URL string — extract from focal object or use directly if string.
                <FsLightbox
                    toggler={toggler}
                    sources={[typeof image === 'string' ? image : image.url]}
                    types={['image']}
                />
            )}
        </section>
    )
}

export default SplitImageText
