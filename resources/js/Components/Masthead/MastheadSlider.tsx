import { Swiper, SwiperSlide } from 'swiper/react'
import { Autoplay, Pagination, Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/pagination'
import 'swiper/css/navigation'

interface MastheadSliderProps {
    slides: string[]
    title: string
    subtitle?: string
}

const MastheadSlider = ({ slides, title, subtitle }: MastheadSliderProps) => {
    return (
        <div className="relative w-full h-[50vh] md:h-[60vh] lg:h-[70vh] overflow-hidden">
            <Swiper
                modules={[Autoplay, Pagination, Navigation]}
                autoplay={{ delay: 5000 }}
                loop
                className="w-full h-full"
            >
                {slides.map((slide, index) => (
                    <SwiperSlide key={index}>
                        <img src={slide} alt="" className="w-full h-full object-cover" />
                    </SwiperSlide>
                ))}
            </Swiper>
            <div className="absolute inset-0 bg-primary z-10 pointer-events-none" style={{ opacity: 0.4 }} />
            <div className="absolute inset-0 z-20 container mx-auto flex flex-col justify-end pb-8 pointer-events-none">
                <h1 className="text-white text-4xl md:text-5xl lg:text-6xl font-bold drop-shadow-lg">
                    {title}
                </h1>
                {subtitle && (
                    <p className="text-white text-xl md:text-2xl mt-2 drop-shadow-md">{subtitle}</p>
                )}
            </div>
        </div>
    )
}

export default MastheadSlider
