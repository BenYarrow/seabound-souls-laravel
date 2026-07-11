// RelatedSpotGuides — closing "explore more" slider for the spot-guide page.
// Shows other guides in the same country (or continent as a fallback) as a
// Swiper carousel of richer cards. Renders nothing when there are no guides.
// Data shape is produced by SpotGuideController::show (related_spot_guides prop).

import { Link } from '@inertiajs/react'
import { Swiper, SwiperSlide } from 'swiper/react'
import { Navigation } from 'swiper/modules'
import { faChevronLeft, faChevronRight, faWind, faCompass } from '@fortawesome/free-solid-svg-icons'

import CoverImage from '@/Components/Common/CoverImage'
import Icon from '@/Components/Common/Icon'
import AnimateInView from '@/Components/Common/AnimateInView'
import type { FocalImage } from '@/types/media'

import 'swiper/css'
import 'swiper/css/navigation'

/** One related guide, as shaped by the controller. */
interface RelatedGuide {
    id: number
    title: string
    slug: string
    country: { name: string } | null
    thumbnail: FocalImage | null
    intro_snippet: string
    overview: {
        wind_conditions: string | null
        best_direction: string | null
    }
}

interface RelatedSpotGuidesProps {
    /** 'country' | 'continent' — which relation produced the set (unused for display; label carries the text). */
    relation: 'country' | 'continent' | null
    /** Human label for the heading, e.g. "Greece" or "Europe". */
    label: string | null
    guides: RelatedGuide[]
}

/**
 * Render the related-guides slider. Returns null when there is nothing to show
 * so the section disappears entirely (matches the controller's empty case).
 */
const RelatedSpotGuides = ({ label, guides }: RelatedSpotGuidesProps) => {
    if (!guides || guides.length === 0) return null

    return (
        <section className="bg-cream">
            <div className="container mx-auto py-14 lg:py-18">
                {/* Heading — matches the page's SectionHeading treatment. */}
                <div className="flex items-start gap-4 mb-8 lg:mb-10">
                    <div className="mt-2 w-1 h-10 bg-orange rounded-full shrink-0" />
                    <h2
                        className="font-display leading-none tracking-wide text-secondary"
                        style={{ fontSize: 'clamp(2rem, 4vw, 3.5rem)' }}
                    >
                        {label ? `More Spots in ${label}` : 'More Spots'}
                    </h2>
                </div>

                <AnimateInView tag="div">
                    <Swiper
                        modules={[Navigation]}
                        spaceBetween={20}
                        slidesPerView={1.1}
                        breakpoints={{
                            768: { slidesPerView: 2 },
                            1024: { slidesPerView: 3 },
                        }}
                        navigation={{
                            nextEl: '.swiper-related-next',
                            prevEl: '.swiper-related-prev',
                        }}
                    >
                        {guides.map((guide) => (
                            <SwiperSlide key={guide.id} className="!h-auto">
                                <Link
                                    href={`/destinations/${guide.slug}`}
                                    className="group flex flex-col h-full bg-white overflow-hidden shadow-sm hover:shadow-lg transition-shadow duration-500"
                                >
                                    <div className="relative aspect-[4/3] overflow-hidden bg-primary-darker">
                                        {guide.thumbnail && (
                                            <CoverImage
                                                image={guide.thumbnail}
                                                alt={guide.title}
                                                className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-700 ease-out"
                                            />
                                        )}
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />
                                        {guide.country && (
                                            <span className="absolute top-3 left-3 text-white/90 text-[10px] uppercase tracking-[0.15em]">
                                                {guide.country.name}
                                            </span>
                                        )}
                                    </div>

                                    <div className="flex flex-col flex-1 p-5">
                                        <h3
                                            className="font-display text-secondary leading-none tracking-wide"
                                            style={{ fontSize: 'clamp(1.15rem, 2vw, 1.5rem)' }}
                                        >
                                            {guide.title}
                                        </h3>

                                        {guide.intro_snippet && (
                                            <p className="text-secondary/60 text-sm mt-2 line-clamp-3 leading-relaxed">
                                                {guide.intro_snippet}
                                            </p>
                                        )}

                                        {/* spot_overview badges — render only those present. */}
                                        {(guide.overview.wind_conditions || guide.overview.best_direction) && (
                                            <div className="mt-4 flex flex-wrap gap-2">
                                                {guide.overview.wind_conditions && (
                                                    <span className="inline-flex items-center gap-1.5 bg-primary-lightest text-primary text-[11px] uppercase tracking-wide px-2.5 py-1 rounded-full">
                                                        <Icon icon={faWind} size="size-3" />
                                                        {guide.overview.wind_conditions}
                                                    </span>
                                                )}
                                                {guide.overview.best_direction && (
                                                    <span className="inline-flex items-center gap-1.5 bg-primary-lightest text-primary text-[11px] uppercase tracking-wide px-2.5 py-1 rounded-full">
                                                        <Icon icon={faCompass} size="size-3" />
                                                        {guide.overview.best_direction}
                                                    </span>
                                                )}
                                            </div>
                                        )}

                                        <div className="mt-auto pt-4 flex items-center text-primary text-[10px] uppercase tracking-[0.15em]">
                                            View guide
                                            <span className="ml-2 h-px w-6 bg-primary group-hover:w-10 transition-all duration-500 ease-out" />
                                        </div>
                                    </div>
                                </Link>
                            </SwiperSlide>
                        ))}
                    </Swiper>

                    {/* Nav arrows — hidden when everything fits without scrolling is handled by Swiper. */}
                    <div className="mt-8 flex items-center gap-4">
                        <button
                            type="button"
                            aria-label="Previous"
                            className="swiper-related-prev hover:scale-110 transition-transform duration-300 text-secondary"
                        >
                            <Icon icon={faChevronLeft} />
                        </button>
                        <button
                            type="button"
                            aria-label="Next"
                            className="swiper-related-next hover:scale-110 transition-transform duration-300 text-secondary"
                        >
                            <Icon icon={faChevronRight} />
                        </button>
                    </div>
                </AnimateInView>
            </div>
        </section>
    )
}

export default RelatedSpotGuides
