/**
 * Gallery — a content-builder block rendering a swipeable image carousel
 * (Swiper), either as a compact thumbnail strip (`thumbnailsOnly`) or the
 * full pagination/navigation carousel. Clicking any slide opens the same
 * image set in an fslightbox-react lightbox, starting at the clicked index.
 *
 * Credited-vs-uncredited slides: fslightbox-react@2 has no caption support
 * and no onSlideChange callback (verified against the installed package),
 * so the only way to show a photographer credit inside the lightbox is a
 * CUSTOM SOURCE — a JSX element (`CreditedLightboxSlide`) in place of the
 * usual URL string. A credited image becomes that custom element; an
 * uncredited image stays a plain URL string so the library's own sizing and
 * zoom handling keeps covering the common case (most images have no credit).
 */
import { useEffect, useRef, useState } from 'react'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { Swiper, SwiperSlide } from 'swiper/react'
import { Navigation, Pagination } from 'swiper/modules'
import FsLightbox from 'fslightbox-react'

import Icon from '../Common/Icon'
import AnimateInView from '../Common/AnimateInView'
import BlockWrapper from '../Common/BlockWrapper'
import CoverImage from '@/Components/Common/CoverImage'
import CreditedLightboxSlide from '@/Components/Common/CreditedLightboxSlide'
import type { FocalImage } from '@/types/media'

import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

interface GalleryProps {
    /** Each image is a focal-bearing object; `.url` is used for the lightbox source. */
    images: FocalImage[]
    thumbnailsOnly?: boolean
}

const Gallery = ({ images, thumbnailsOnly }: GalleryProps) => {
    const swiperRef = useRef<any>(null)
    const [toggler, setToggler] = useState(false)
    const [currentImageIndex, setCurrentImageIndex] = useState(0)

    useEffect(() => {
        if (swiperRef.current && images && images.length > 0) {
            swiperRef.current.update()
            swiperRef.current.navigation?.update()
            swiperRef.current.pagination?.update()
        }
    }, [images])

    if (!images || images.length === 0) return null

    const lightboxSources = images.map((image) =>
        image.credit ? (
            <CreditedLightboxSlide url={image.url} alt={image.alt ?? ''} credit={image.credit} />
        ) : (
            image.url
        )
    )

    return (
        <>
            <AnimateInView tag="div">
                {thumbnailsOnly ? (
                    <BlockWrapper>
                        <Swiper
                            spaceBetween={10}
                            slidesPerView={1.25}
                            slidesPerGroup={1}
                            breakpoints={{
                                768: { slidesPerView: 3, slidesPerGroup: 3, spaceBetween: 20 },
                                1024: { slidesPerView: 4, slidesPerGroup: 4, spaceBetween: 20 },
                            }}
                        >
                            {images.map((img, index) => (
                                <SwiperSlide key={index} className="!aspect-square">
                                    <button
                                        onClick={() => {
                                            setCurrentImageIndex(index)
                                            setToggler(!toggler)
                                        }}
                                        className="w-full h-full block relative"
                                    >
                                        <CoverImage
                                            image={img}
                                            className="w-full h-full"
                                        />
                                    </button>
                                </SwiperSlide>
                            ))}
                        </Swiper>
                    </BlockWrapper>
                ) : (
                    <Swiper
                        modules={[Navigation, Pagination]}
                        slidesPerView={1.5}
                        centeredSlides
                        pagination={{
                            el: '.swiper-gallery-pagination',
                            clickable: true,
                            renderBullet: (_index: number, className: string) =>
                                `<span class="${className} custom-pagination-bullet"></span>`,
                        }}
                        navigation={{
                            nextEl: '.swiper-gallery-next',
                            prevEl: '.swiper-gallery-prev',
                        }}
                        onSwiper={(swiper) => {
                            swiperRef.current = swiper
                        }}
                        className="!py-8"
                        id="gallery"
                    >
                        {images.map((img, index) => (
                            <SwiperSlide key={index} className="swiper-gallery">
                                <button
                                    onClick={() => {
                                        setCurrentImageIndex(index)
                                        setToggler(!toggler)
                                    }}
                                    className="w-full h-full flex justify-center"
                                >
                                    <CoverImage
                                        image={img}
                                        className="gallery-image w-full h-full aspect-square lg:aspect-video lg:w-[60vw]"
                                    />
                                </button>
                            </SwiperSlide>
                        ))}

                        <div className="mt-12 w-full flex justify-center">
                            <div className="max-w-max flex items-center gap-4 lg:gap-8">
                                <button className="swiper-gallery-prev hover:scale-[1.1] transition duration-300">
                                    <Icon icon={faChevronLeft} />
                                </button>
                                <div className="swiper-gallery-pagination !mb-2.5" />
                                <button className="swiper-gallery-next hover:scale-[1.1] transition duration-300">
                                    <Icon icon={faChevronRight} />
                                </button>
                            </div>
                        </div>
                    </Swiper>
                )}
            </AnimateInView>

            <FsLightbox
                toggler={toggler}
                sources={lightboxSources}
                slide={currentImageIndex + 1}
                types={images.map(() => 'image') as any}
            />
        </>
    )
}

export default Gallery
