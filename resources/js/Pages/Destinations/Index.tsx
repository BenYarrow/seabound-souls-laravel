import { useMemo, useState } from 'react'
import { Link } from '@inertiajs/react'
import { groupBy } from 'lodash'

import Layout from '@/Layouts/Layout'
import DestinationCard from '@/Components/Common/DestinationCard'
import FeaturedHero from '@/Components/Common/FeaturedHero'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import DestinationsMap from '@/Components/Map/DestinationsMap'
import FilterDataset, { SelectOption } from '@/Components/Destinations/FilterDataset'
import AllDestinationsWindChart from '@/Components/Destinations/AllDestinationsWindChart'
import AllDestinationsTempChart from '@/Components/Destinations/AllDestinationsTempChart'
import AnimateInView from '@/Components/Common/AnimateInView'
import { getSpotGuideColours } from '@/Helpers/colours'
import type { WeatherDataset } from '@/Helpers/weatherDataHelpers'
import type { FocalImage } from '@/types/media'

/** Author attribution: 'house' (us) shows the brand; 'contributor' shows the name (and links to their profile). */
interface Author {
    kind: 'house' | 'contributor'
    name: string | null
    slug: string | null
}

interface SpotGuide {
    id: number
    title: string
    slug: string
    latitude: number | null
    longitude: number | null
    country: { name: string; slug: string; continent: string } | null
    /** Focal-bearing image object (or null when no thumbnail). */
    thumbnail: FocalImage | null
    author: Author
}

interface Props {
    spotGuides: SpotGuide[]
    weatherData: WeatherDataset
    /** True once a published contributor guide exists — turns on the provenance bylines. */
    showProvenance: boolean
    /** Masthead from the "destinations" landing Page; null falls back to the first guide's thumbnail. */
    static_masthead: FocalImage | null
    featuredSpotGuide: {
        id: number
        title: string
        slug: string
        country: string | null
        thumbnail: FocalImage | null
    } | null
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const CONTINENT_LABELS: Record<string, string> = {
    africa: 'Africa',
    asia: 'Asia',
    europe: 'Europe',
    'north-america': 'North America',
    'south-america': 'South America',
    oceania: 'Oceania',
}

/**
 * Display priority for continent sections — Europe first (most of our visitors),
 * then Africa, then the rest. Continents not listed fall to the end alphabetically.
 */
const CONTINENT_ORDER = ['europe', 'africa', 'asia', 'north-america', 'south-america', 'oceania']

const Index = ({ spotGuides, weatherData, showProvenance, static_masthead, featuredSpotGuide, meta }: Props) => {
    const titles = Object.keys(weatherData).sort()
    const colours = useMemo(() => getSpotGuideColours(titles), [titles])

    const destinationOptions: SelectOption[] = titles.map((t) => ({ label: t, value: t }))

    const years = useMemo(() => {
        const allYears = new Set<number>()
        Object.values(weatherData).forEach((yearMap) => {
            Object.keys(yearMap).forEach((y) => allYears.add(Number(y)))
        })
        return [...allYears].sort((a, b) => b - a)
    }, [weatherData])

    const currentYear = new Date().getFullYear()
    const defaultYear = years.includes(currentYear) ? currentYear : years[0]

    const yearOptions = years.map((y) => ({ label: y, value: y }))

    const [activeYear, setActiveYear] = useState<number>(defaultYear)
    const [activeDestinations, setActiveDestinations] = useState<SelectOption[]>(destinationOptions)
    const [showAverageGustData, setShowAverageGustData] = useState(false)
    const [activeWindUnit, setActiveWindUnit] = useState('kts')

    const handleReset = () => {
        setActiveYear(defaultYear)
        setActiveDestinations(destinationOptions)
        setShowAverageGustData(false)
        setActiveWindUnit('kts')
    }

    const groupedByContinent = useMemo(
        () => groupBy(spotGuides.filter((s) => s.country), (s) => s.country!.continent),
        [spotGuides]
    )

    // Prefer the "destinations" landing-page masthead; fall back to the first guide
    // with a thumbnail. null is fine — StaticMasthead handles it.
    const mastheadImage = static_masthead ?? spotGuides.find((s) => s.thumbnail)?.thumbnail ?? null

    // Continent sections in display priority (Europe, Africa, then the rest). Within
    // each, guides arrive already ranked windiest-first from the server.
    const orderedContinents = Object.entries(groupedByContinent).sort(([a], [b]) => {
        const rank = (continent: string) => {
            const index = CONTINENT_ORDER.indexOf(continent)
            return index === -1 ? CONTINENT_ORDER.length : index
        }
        return rank(a) - rank(b) || a.localeCompare(b)
    })

    // "Month Year" label for the ordering note — client-side "now", matching the
    // server's now()-based wind ranking.
    const orderingPeriod = new Date().toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>

            {/* ─── Masthead ─── */}
            <StaticMasthead
                imageUrl={mastheadImage}
                title="Destinations"
                eyebrow="Windsurfing around the world"
            />

            {/* ─── Editorial intro ─── */}
            <section className="bg-cream">
                <div className="container mx-auto py-16 lg:py-20">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-20 items-end">
                        <div>
                            <span className="block w-8 h-0.5 bg-orange mb-5" />
                            <h2
                                className="font-display text-secondary leading-none tracking-wide"
                                style={{ fontSize: 'clamp(2.5rem, 6vw, 5rem)' }}
                            >
                                Explore The World's Best Windsurfing Spots
                            </h2>
                        </div>
                        <div className="space-y-4 lg:pb-1">
                            <p className="text-gray-500 text-base lg:text-lg leading-relaxed">
                                From the trade winds of the Canary Islands to the thermal gusts of Egypt,
                                discover curated destination guides with everything you need to plan
                                your next adventure.
                            </p>
                            <p className="text-gray-400 text-sm">
                                {spotGuides.length} destinations across{' '}
                                {Object.keys(groupedByContinent).length} continents
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {/* ─── Featured destination ─── */}
            {featuredSpotGuide && (
                <section className="bg-white">
                    <div className="container mx-auto py-14 lg:py-16">
                        <FeaturedHero
                            image={featuredSpotGuide.thumbnail}
                            eyebrow="Featured Destination"
                            title={featuredSpotGuide.title}
                            metaLabel={featuredSpotGuide.country}
                            href={`/destinations/${featuredSpotGuide.slug}`}
                            ctaLabel="Explore guide"
                        />
                    </div>
                </section>
            )}

            {/* ─── Map ─── */}
            <DestinationsMap spotGuides={spotGuides} />

            {/* ─── Ordering note — explains how the continent listings below are sorted ─── */}
            <section className="bg-white">
                <div className="container mx-auto pt-14 lg:pt-18">
                    <p className="text-gray-500 text-sm italic leading-relaxed border-l-2 border-primary-lighter pl-3 max-w-2xl">
                        Ordered by gusts for {orderingPeriod} — each region's spots are ranked on
                        this year's readings, so wherever's firing now rises to the top.
                    </p>
                </div>
            </section>

            {/* ─── Continent sections ─── */}
            {orderedContinents.map(([continent, guides], sectionIndex) => (
                <section key={continent} className={sectionIndex % 2 === 0 ? 'bg-white' : 'bg-cream'}>
                    <div className="container mx-auto pt-14 lg:pt-18 pb-0">

                        {/* Continent heading */}
                        <div className="flex items-center gap-5 mb-10 lg:mb-12">
                            <div className="flex items-start gap-4">
                                <div className="mt-2 w-1 h-10 bg-orange rounded-full shrink-0" />
                                <h2
                                    className="font-display text-secondary leading-none tracking-wide"
                                    style={{ fontSize: 'clamp(2.2rem, 5vw, 4.5rem)' }}
                                >
                                    {CONTINENT_LABELS[continent] || continent}
                                </h2>
                            </div>
                            <div className="flex-1 h-px bg-gradient-to-r from-secondary/15 to-transparent hidden md:block" />
                            <span className="text-secondary/30 text-sm font-medium tabular-nums hidden md:block">
                                {guides.length} {guides.length === 1 ? 'spot' : 'spots'}
                            </span>
                        </div>
                    </div>

                    {/* Card grid — contained but flush */}
                    <AnimateInView
                        tag="ul"
                        animateChildren
                        classes="container mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3"
                    >
                        {guides.map((guide) => (
                            <li key={guide.id} className="aspect-square">
                                <DestinationCard
                                    title={guide.title}
                                    slug={guide.slug}
                                    thumbnail={guide.thumbnail}
                                    countryName={guide.country?.name}
                                    byline={
                                        showProvenance
                                            ? guide.author.kind === 'contributor' && guide.author.name
                                                ? `By ${guide.author.name}`
                                                : 'Seabound Souls'
                                            : null
                                    }
                                />
                            </li>
                        ))}
                    </AnimateInView>

                    {/* Bottom spacing */}
                    <div className="pb-6" />
                </section>
            ))}

            {/* ─── Weather data ─── */}
            {titles.length > 0 && (
                // Pale-teal "data" zone — a distinct light tone so it reads as
                // its own section, separated from the white/cream listings above.
                <section className="bg-primary-lightest">
                    {/* Section header */}
                    <div className="container mx-auto pt-16 lg:pt-20 pb-10 lg:pb-12">
                        <div className="flex items-start gap-4">
                            <div className="mt-2 w-1 h-12 bg-orange rounded-full shrink-0" />
                            <div>
                                <h2
                                    className="font-display text-secondary leading-none tracking-wide"
                                    style={{ fontSize: 'clamp(2.5rem, 5vw, 4.5rem)' }}
                                >
                                    Wind & Weather Data
                                </h2>
                                <p className="text-secondary/50 text-sm mt-2">
                                    Historical monthly averages across all destinations
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* Filter bar */}
                    <FilterDataset
                        yearOptions={yearOptions}
                        destinationOptions={destinationOptions}
                        activeYear={activeYear}
                        setActiveYear={setActiveYear}
                        activeDestinations={activeDestinations}
                        setActiveDestinations={setActiveDestinations}
                        onReset={handleReset}
                    />

                    {/* Charts */}
                    <div className="container mx-auto py-10 lg:py-14 space-y-8">
                        <AllDestinationsWindChart
                            weatherData={weatherData}
                            activeYear={activeYear}
                            activeDestinations={activeDestinations}
                            showAverageGustData={showAverageGustData}
                            activeWindUnit={activeWindUnit}
                            setActiveWindUnit={setActiveWindUnit}
                            setShowAverageGustData={setShowAverageGustData}
                            colours={colours}
                        />
                        <AllDestinationsTempChart
                            weatherData={weatherData}
                            activeYear={activeYear}
                            activeDestinations={activeDestinations}
                            colours={colours}
                        />
                    </div>
                </section>
            )}

        </Layout>
    )
}

export default Index
