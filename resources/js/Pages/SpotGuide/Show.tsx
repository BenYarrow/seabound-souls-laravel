import { useMemo } from 'react'
import { Link } from '@inertiajs/react'
import { faDirections } from '@fortawesome/free-solid-svg-icons'

import Layout from '@/Layouts/Layout'
import CoverImage from '@/Components/Common/CoverImage'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import ContentBuilder from '@/Components/ContentBuilder'
import SpotOverview from '@/Components/Common/SpotOverview'
import LiveWeatherData from '@/Components/Common/LiveWeatherData'
import Icon from '@/Components/Common/Icon'
import AnimateInView from '@/Components/Common/AnimateInView'
import ContentWithBackgroundImage from '@/Components/Content/ContentWithBackgroundImage'
import Gallery from '@/Components/Content/Gallery'
import SpotGuideStatistics from '@/Components/SpotGuide/SpotGuideStatistics'
import SpotGuideMap, { type MapLocation } from '@/Components/SpotGuide/SpotGuideMap'
import SpotGuideNav from '@/Components/SpotGuide/SpotGuideNav'
import RelatedSpotGuides from '@/Components/SpotGuide/RelatedSpotGuides'
import { buildSpotGuideSections } from '@/Helpers/spotGuideSections'
import type { FocalImage } from '@/types/media'

interface Recommendation {
    id: number
    name: string
    description?: string
    url?: string
    latitude?: number | null
    longitude?: number | null
    thumbnail?: FocalImage | null
}

interface WindsurfLocation {
    id: number
    name: string
    description?: string
    latitude?: number | null
    longitude?: number | null
    thumbnail?: FocalImage | null
}

interface Props {
    spotGuide: {
        id: number
        title: string
        slug: string
        /** Author attribution: 'house' (us) vs a named 'contributor' (links to their profile). */
        author: { kind: 'house' | 'contributor'; name: string | null; slug: string | null; image: FocalImage | null }
        country: { name: string; slug: string; continent: string } | null
        latitude: number | null
        longitude: number | null
        introduction_text: string | null
        spot_overview: Record<string, string> | null
        water_conditions: { content: string; text_right: boolean } | null
        wind_conditions: { content: string; text_right: boolean } | null
        when_to_go: string | null
        where_to_stay_intro: string | null
        where_to_eat_intro: string | null
        travelling_to: { content: string; text_right: boolean } | null
        lessons_and_hire: { content: string; text_right: boolean } | null
        content_blocks: any[] | null
        thumbnail: FocalImage | null
        static_masthead: FocalImage | null
        gallery: FocalImage[]
        water_conditions_bg: FocalImage | null
        wind_conditions_bg: FocalImage | null
        travelling_to_bg: FocalImage | null
        lessons_and_hire_bg: FocalImage | null
        stay_recommendations: Recommendation[]
        eat_recommendations: Recommendation[]
        windsurfing_locations: WindsurfLocation[]
        weather_records: Record<string, any[]>
    }
    related_spot_guides: {
        relation: 'country' | 'continent' | null
        label: string | null
        guides: {
            id: number
            title: string
            slug: string
            thumbnail: FocalImage | null
            intro_snippet: string
            overview: { wind_conditions: string | null; best_direction: string | null }
        }[]
    }
    meta: any
    /** True when rendering an unpublished guide for the owner/author preview. */
    is_preview?: boolean
    /** True once a published contributor guide exists — turns on the provenance byline. */
    showProvenance?: boolean
}

/* ── Section heading ── */
const SectionHeading = ({ children, light = false }: { children: string; light?: boolean }) => (
    <div className="flex items-start gap-4 mb-8 lg:mb-10">
        <div className="mt-2 w-1 h-10 bg-orange rounded-full shrink-0" />
        <h2
            className={`font-display leading-none tracking-wide ${light ? 'text-white' : 'text-secondary'}`}
            style={{ fontSize: 'clamp(2rem, 4vw, 3.5rem)' }}
        >
            {children}
        </h2>
    </div>
)

/* ── Recommendation card grid ── */
const RecommendationCards = ({ items, showDirections = false }: { items: Recommendation[]; showDirections?: boolean }) => (
    <AnimateInView
        tag="ul"
        animateChildren
        classes="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
    >
        {items.map((rec) => (
            <li key={rec.id} className="aspect-[4/3]">
                <div className="group relative w-full h-full overflow-hidden bg-primary-darker">
                    {rec.thumbnail && (
                        <CoverImage
                            image={rec.thumbnail}
                            alt={rec.name}
                            className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-700 ease-out"
                        />
                    )}
                    <div className="absolute inset-0 bg-black/25 group-hover:bg-black/40 transition-colors duration-500" />
                    <div className="absolute inset-0 bg-gradient-to-t from-black/75 via-transparent to-transparent" />

                    <div className="absolute bottom-0 left-0 right-0 p-5">
                        <h3
                            className="font-display text-white leading-none tracking-wide drop-shadow-lg"
                            style={{ fontSize: 'clamp(1.15rem, 2vw, 1.5rem)' }}
                        >
                            {rec.name}
                        </h3>
                        {rec.description && (
                            <p className="text-white/50 text-xs mt-1.5 line-clamp-2 leading-relaxed">{rec.description}</p>
                        )}
                        <div className="mt-3 flex items-center gap-4">
                            {rec.url && (
                                <a
                                    href={rec.url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary-lighter text-[10px] uppercase tracking-[0.15em] hover:text-white transition-colors"
                                >
                                    Visit &rarr;
                                </a>
                            )}
                            {showDirections && rec.latitude && rec.longitude && (
                                <a
                                    href={`https://www.google.com/maps/dir/?api=1&destination=${rec.latitude},${rec.longitude}`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-primary-lighter/60 text-[10px] uppercase tracking-[0.15em] hover:text-white transition-colors flex items-center gap-1"
                                >
                                    <Icon icon={faDirections} size="size-3" />
                                    Directions
                                </a>
                            )}
                        </div>
                        <div className="mt-2 h-px w-0 bg-primary-lighter group-hover:w-10 transition-all duration-500 ease-out" />
                    </div>
                </div>
            </li>
        ))}
    </AnimateInView>
)

/* ──────────────────── Page ──────────────────── */

/**
 * @param is_preview - true when rendering an unpublished guide for the owner/author
 * @param showProvenance - true once a contributor guide exists; turns on the author byline
 */
const Show = ({ spotGuide, related_spot_guides, meta, is_preview = false, showProvenance = false }: Props) => {
    /* Attribution byline: a contributor's name (linked to their profile when they
       have one), or the house brand. */
    const isContributorAuthor = spotGuide.author.kind === 'contributor' && !!spotGuide.author.name
    /* Aggregate all locations for the map */
    const mapLocations = useMemo<MapLocation[]>(() => {
        const locs: MapLocation[] = []
        spotGuide.stay_recommendations.forEach((r) =>
            locs.push({ id: r.id, name: r.name, description: r.description, latitude: r.latitude ?? null, longitude: r.longitude ?? null, thumbnail: r.thumbnail, url: r.url, type: 'stay' })
        )
        spotGuide.eat_recommendations.forEach((r) =>
            locs.push({ id: r.id, name: r.name, description: r.description, latitude: r.latitude ?? null, longitude: r.longitude ?? null, thumbnail: r.thumbnail, url: r.url, type: 'eat' })
        )
        spotGuide.windsurfing_locations.forEach((l) =>
            locs.push({ id: l.id, name: l.name, description: l.description, latitude: l.latitude ?? null, longitude: l.longitude ?? null, thumbnail: l.thumbnail, type: 'windsurf' })
        )
        return locs
    }, [spotGuide])

    /* Present sections for the sticky quick-nav (see buildSpotGuideSections). */
    const navSections = useMemo(() => buildSpotGuideSections(spotGuide), [spotGuide])

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>

            {is_preview && (
                <div
                    role="status"
                    className="sticky top-0 z-50 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-black dark:bg-amber-400"
                >
                    Unpublished preview — this guide is not yet live.
                </div>
            )}

            {/* ── Masthead ── */}
            <div className="relative">
                <StaticMasthead
                    imageUrl={spotGuide.static_masthead}
                    title={spotGuide.title}
                    subtitle={spotGuide.country?.name}
                >
                    {spotGuide.latitude && spotGuide.longitude && (
                        <LiveWeatherData latitude={spotGuide.latitude} longitude={spotGuide.longitude} />
                    )}
                </StaticMasthead>

                {/* Overview: desktop = right sidebar over the image; mobile = block below the image */}
                {spotGuide.spot_overview && (
                    <SpotOverview spotOverview={spotGuide.spot_overview} />
                )}
            </div>

            {/* ── Sticky quick-nav ── */}
            <SpotGuideNav sections={navSections} />

            {/* ── Author attribution — only once a contributor guide exists on the site ──
                Sits below the quick-nav, leading the content.
                Contributor: a prominent card (portrait + name) linking to their profile.
                House: a simple "Written by Seabound Souls" label. */}
            {showProvenance && (
                <div className="bg-cream flex justify-center pt-10 pb-10">
                    {isContributorAuthor ? (
                        (() => {
                            // A contributor always gets the author card: linked to their
                            // profile when they have a slug, plain (non-clickable) otherwise
                            // — never misattributed to the house brand.
                            const cardInner = (
                                <>
                                    {spotGuide.author.image ? (
                                        <span className="w-14 h-14 rounded-full overflow-hidden ring-2 ring-primary-lighter shrink-0">
                                            <CoverImage image={spotGuide.author.image} alt={spotGuide.author.name ?? ''} className="w-full h-full" />
                                        </span>
                                    ) : (
                                        <span className="w-14 h-14 rounded-full bg-primary-lighter shrink-0" />
                                    )}
                                    <span className="text-left">
                                        <span className="block text-[10px] uppercase tracking-[0.35em] text-primary/60">Written by</span>
                                        <span
                                            className="block font-title text-secondary uppercase leading-tight group-hover:text-primary transition-colors duration-200"
                                            style={{ fontSize: 'clamp(1.15rem, 2.2vw, 1.6rem)' }}
                                        >
                                            {spotGuide.author.name}
                                        </span>
                                    </span>
                                </>
                            )

                            return spotGuide.author.slug ? (
                                <Link
                                    href={`/contributors/${spotGuide.author.slug}`}
                                    className="group inline-flex items-center gap-4 rounded-full bg-white py-2 pl-2 pr-6 shadow-md hover:shadow-lg transition-shadow duration-300"
                                >
                                    {cardInner}
                                    <svg className="w-4 h-4 text-primary/50 group-hover:text-primary group-hover:translate-x-1 transition-all duration-200 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                        <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                                    </svg>
                                </Link>
                            ) : (
                                <div className="inline-flex items-center gap-4 rounded-full bg-white py-2 pl-2 pr-6 shadow-md">
                                    {cardInner}
                                </div>
                            )
                        })()
                    ) : (
                        <div className="inline-flex flex-col items-center">
                            <span className="text-[10px] uppercase tracking-[0.35em] text-primary/60">Written by</span>
                            <span className="font-title text-secondary uppercase leading-tight mt-1" style={{ fontSize: 'clamp(1.15rem, 2.2vw, 1.6rem)' }}>
                                Seabound Souls
                            </span>
                        </div>
                    )}
                </div>
            )}

            {/* ── Introduction ── */}
            {spotGuide.introduction_text && (
                <section id="introduction" className="bg-cream scroll-mt-14">
                    <div className="container mx-auto py-14 lg:py-18">
                        <div
                            className="prose prose-lg max-w-none prose-headings:font-display prose-headings:tracking-wide prose-headings:text-secondary prose-a:text-primary"
                            dangerouslySetInnerHTML={{ __html: spotGuide.introduction_text }}
                        />
                    </div>
                </section>
            )}

            {/* ── Gallery ── */}
            {spotGuide.gallery.length > 0 && (
                <div id="gallery" className="scroll-mt-14">
                    <Gallery images={spotGuide.gallery} />
                </div>
            )}

            {/* ── Water Conditions ── */}
            {spotGuide.water_conditions?.content && (
                <div id="water-conditions" className="scroll-mt-14">
                    <ContentWithBackgroundImage
                        backgroundImageUrl={spotGuide.water_conditions_bg}
                        content={spotGuide.water_conditions.content}
                        textRight={spotGuide.water_conditions.text_right}
                        title="Water Conditions"
                    />
                </div>
            )}

            {/* ── Wind Conditions ── */}
            {spotGuide.wind_conditions?.content && (
                <div id="wind-conditions" className="scroll-mt-14">
                    <ContentWithBackgroundImage
                        backgroundImageUrl={spotGuide.wind_conditions_bg}
                        content={spotGuide.wind_conditions.content}
                        textRight={spotGuide.wind_conditions.text_right}
                        title="Wind Conditions"
                    />
                </div>
            )}

            {/* ── Content Builder blocks ── */}
            {spotGuide.content_blocks && <ContentBuilder blocks={spotGuide.content_blocks} />}

            {/* ── When To Go ── */}
            {spotGuide.when_to_go && (
                <section id="when-to-go" className="bg-white scroll-mt-14">
                    <div className="container mx-auto py-14 lg:py-18">
                        <SectionHeading>When To Go</SectionHeading>
                        <div
                            className="prose prose-lg max-w-none prose-headings:font-display prose-headings:tracking-wide prose-headings:text-secondary prose-a:text-primary"
                            dangerouslySetInnerHTML={{ __html: spotGuide.when_to_go }}
                        />
                    </div>
                </section>
            )}

            {/* ── Weather Statistics ── */}
            {Object.keys(spotGuide.weather_records).length > 0 && (
                <div id="weather" className="scroll-mt-14">
                    <SpotGuideStatistics weatherRecords={spotGuide.weather_records} />
                </div>
            )}

            {/* ── Where To Stay ── */}
            {(spotGuide.where_to_stay_intro || spotGuide.stay_recommendations.length > 0) && (
                <section id="where-to-stay" className="bg-cream scroll-mt-14">
                    <div className="container mx-auto pt-14 lg:pt-18 pb-0">
                        <SectionHeading>Where To Stay</SectionHeading>
                        {spotGuide.where_to_stay_intro && (
                            <div
                                className="prose prose-lg max-w-none mb-10 prose-headings:font-display prose-headings:tracking-wide prose-headings:text-secondary prose-a:text-primary"
                                dangerouslySetInnerHTML={{ __html: spotGuide.where_to_stay_intro }}
                            />
                        )}
                    </div>
                    {spotGuide.stay_recommendations.length > 0 && (
                        <div className="container mx-auto pb-6">
                            <RecommendationCards items={spotGuide.stay_recommendations} showDirections />
                        </div>
                    )}
                </section>
            )}

            {/* ── Where To Eat ── */}
            {(spotGuide.where_to_eat_intro || spotGuide.eat_recommendations.length > 0) && (
                <section id="where-to-eat" className="bg-white scroll-mt-14">
                    <div className="container mx-auto pt-14 lg:pt-18 pb-0">
                        <SectionHeading>Where To Eat</SectionHeading>
                        {spotGuide.where_to_eat_intro && (
                            <div
                                className="prose prose-lg max-w-none mb-10 prose-headings:font-display prose-headings:tracking-wide prose-headings:text-secondary prose-a:text-primary"
                                dangerouslySetInnerHTML={{ __html: spotGuide.where_to_eat_intro }}
                            />
                        )}
                    </div>
                    {spotGuide.eat_recommendations.length > 0 && (
                        <div className="container mx-auto pb-6">
                            <RecommendationCards items={spotGuide.eat_recommendations} showDirections />
                        </div>
                    )}
                </section>
            )}

            {/* ── Windsurfing Spots ── */}
            {spotGuide.windsurfing_locations.length > 0 && (
                <section id="windsurfing-spots" className="bg-cream scroll-mt-14">
                    <div className="container mx-auto pt-14 lg:pt-18 pb-0">
                        <SectionHeading>Windsurfing Spots</SectionHeading>
                    </div>
                    <div className="container mx-auto pb-6">
                        <RecommendationCards items={spotGuide.windsurfing_locations} />
                    </div>
                </section>
            )}

            {/* ── Interactive Map ── */}
            {spotGuide.latitude && spotGuide.longitude && mapLocations.length > 0 && (
                <section id="explore-the-area" className="bg-secondary scroll-mt-14">
                    <div className="container mx-auto pt-14 lg:pt-16 pb-10 lg:pb-12">
                        <div className="flex items-start gap-4">
                            <div className="mt-2 w-1 h-10 bg-orange rounded-full shrink-0" />
                            <div>
                                <h2
                                    className="font-display text-white leading-none tracking-wide"
                                    style={{ fontSize: 'clamp(2rem, 4vw, 3.5rem)' }}
                                >
                                    Explore The Area
                                </h2>
                                <p className="text-white/35 text-sm mt-2">
                                    Hotels, restaurants & windsurfing spots
                                </p>
                            </div>
                        </div>
                    </div>
                    <SpotGuideMap
                        latitude={spotGuide.latitude}
                        longitude={spotGuide.longitude}
                        locations={mapLocations}
                    />
                    <div className="pb-8" />
                </section>
            )}

            {/* ── Travelling To ── */}
            {spotGuide.travelling_to?.content && (
                <div id="getting-there" className="scroll-mt-14">
                    <ContentWithBackgroundImage
                        backgroundImageUrl={spotGuide.travelling_to_bg}
                        content={spotGuide.travelling_to.content}
                        textRight={spotGuide.travelling_to.text_right}
                        title="Getting There"
                    />
                </div>
            )}

            {/* ── Lessons & Hire ── */}
            {spotGuide.lessons_and_hire?.content && (
                <div id="lessons-and-hire" className="scroll-mt-14">
                    <ContentWithBackgroundImage
                        backgroundImageUrl={spotGuide.lessons_and_hire_bg}
                        content={spotGuide.lessons_and_hire.content}
                        textRight={spotGuide.lessons_and_hire.text_right}
                        title="Lessons & Hire"
                    />
                </div>
            )}

            {/* ── Related Spot Guides ── */}
            <RelatedSpotGuides
                relation={related_spot_guides.relation}
                label={related_spot_guides.label}
                guides={related_spot_guides.guides}
            />

        </Layout>
    )
}

export default Show
