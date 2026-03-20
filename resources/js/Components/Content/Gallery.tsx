import { useEffect, useRef, useState } from 'react'
import { faChevronLeft, faChevronRight } from '@fortawesome/free-solid-svg-icons'
import { Swiper, SwiperSlide } from 'swiper/react'
import { Navigation, Pagination } from 'swiper/modules'
import FsLightbox from 'fslightbox-react'

import Icon from '../Common/Icon'
import AnimateInView from '../Common/AnimateInView'
import BlockWrapper from '../Common/BlockWrapper'

import 'swiper/css'
import 'swiper/css/navigation'
import 'swiper/css/pagination'

interface GalleryImage {
    url: string
    alt: string
}

interface GalleryProps {
    images: GalleryImage[]
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

    const lightboxSources = images.map((img) => img.url)

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
                                        <img
                                            src={img.url}
                                            alt={img.alt}
                                            className="w-full h-full object-cover"
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
                                    <img
                                        src={img.url}
                                        alt={img.alt}
                                        className="gallery-image w-full h-full aspect-square lg:aspect-video lg:w-[60vw] object-cover"
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
