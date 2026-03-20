import { useState } from 'react'
import { Swiper, SwiperSlide } from 'swiper/react'
import { Autoplay } from 'swiper/modules'
import 'swiper/css'

const DELAY = 5000

interface MastheadSliderProps {
    slides: string[]
    title: string
    subtitle?: string
}

const MastheadSlider = ({ slides, title, subtitle }: MastheadSliderProps) => {
    const [activeIndex, setActiveIndex] = useState(0)
    const [fillProgress, setFillProgress] = useState(0)

    return (
        <div className="relative w-full h-screen overflow-hidden">
            <Swiper
                modules={[Autoplay]}
                autoplay={{ delay: DELAY, disableOnInteraction: false }}
                loop
                speed={1400}
                onSlideChangeTransitionStart={() => setFillProgress(0)}
                onRealIndexChange={(swiper) => setActiveIndex(swiper.realIndex)}
                onAutoplayTimeLeft={(_s, time) => setFillProgress((DELAY - time) / DELAY)}
                className="masthead-slider w-full h-full"
            >
                {slides.map((slide, index) => (
                    <SwiperSlide key={index}>
                        <img
                            src={slide}
                            alt=""
                            className="w-full h-full object-cover"
                        />
                    </SwiperSlide>
                ))}
            </Swiper>

            {/* Teal colour grade overlay */}
            <div className="absolute inset-0 bg-primary/20 z-10 pointer-events-none mix-blend-multiply" />

            {/* Bottom fade — deepens under the title */}
            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent z-10 pointer-events-none" />

            {/* Top fade — softens nav area */}
            <div className="absolute top-0 left-0 right-0 h-40 bg-gradient-to-b from-black/40 to-transparent z-10 pointer-events-none" />

            {/* Hero content */}
            <div className="absolute inset-0 z-20 container mx-auto flex flex-col justify-end pointer-events-none">
                <div className="pb-24 md:pb-28 lg:pb-32">
                    <p className="text-primary-lighter text-xs md:text-sm uppercase tracking-[0.4em] mb-3 font-light">
                        Your windsurfing destination guide
                    </p>
                    <h1
                        className="font-title text-white uppercase leading-[0.9] drop-shadow-2xl"
                        style={{ fontSize: 'clamp(3.5rem, 11vw, 9rem)' }}
                    >
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="text-white/75 text-lg md:text-xl mt-4 max-w-lg">{subtitle}</p>
                    )}
                </div>
            </div>

            {/* Bottom bar */}
            <div className="absolute bottom-0 left-0 right-0 z-30 container mx-auto pb-8 flex items-end justify-between">

                {/* Slide progress lines */}
                <div className="flex items-center gap-2">
                    {slides.map((_, i) => (
                        <div
                            key={i}
                            className="relative h-[2px] overflow-hidden bg-white/25 transition-all duration-300"
                            style={{ width: i === activeIndex ? '3.5rem' : '1.5rem' }}
                        >
                            <span
                                className="absolute inset-y-0 left-0 bg-white"
                                style={{
                                    width: i === activeIndex
                                        ? `${fillProgress * 100}%`
                                        : i < activeIndex ? '100%' : '0%',
                                }}
                            />
                        </div>
                    ))}
                </div>

                {/* Scroll indicator (centred) */}
                <div className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 animate-scroll-nudge pointer-events-none">
                    <div className="w-px h-8 bg-white/40" />
                    <span className="text-white/40 text-[9px] uppercase tracking-[0.35em]">Scroll</span>
                </div>

                {/* Slide counter */}
                <div className="flex items-center gap-1.5 text-white/50">
                    <span className="text-white text-sm font-medium tabular-nums">
                        {String(activeIndex + 1).padStart(2, '0')}
                    </span>
                    <span className="text-xs text-white/30">/</span>
                    <span className="text-xs tabular-nums">
                        {String(slides.length).padStart(2, '0')}
                    </span>
                </div>
            </div>
        </div>
    )
}

export default MastheadSlider
