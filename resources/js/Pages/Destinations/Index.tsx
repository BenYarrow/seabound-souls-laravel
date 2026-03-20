import { useMemo, useState } from 'react'
import { Link } from '@inertiajs/react'
import { groupBy } from 'lodash'

import Layout from '@/Layouts/Layout'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import DestinationsMap from '@/Components/Map/DestinationsMap'
import FilterDataset, { SelectOption } from '@/Components/Destinations/FilterDataset'
import AllDestinationsWindChart from '@/Components/Destinations/AllDestinationsWindChart'
import AllDestinationsTempChart from '@/Components/Destinations/AllDestinationsTempChart'
import AnimateInView from '@/Components/Common/AnimateInView'
import { getSpotGuideColours } from '@/helpers/colours'
import type { WeatherDataset } from '@/helpers/weatherDataHelpers'

interface SpotGuide {
    id: number
    title: string
    slug: string
    latitude: number | null
    longitude: number | null
    country: { name: string; slug: string; continent: string } | null
    thumbnail: string
}

interface Props {
    spotGuides: SpotGuide[]
    weatherData: WeatherDataset
    meta: { title: string; description: string }
}

const CONTINENT_LABELS: Record<string, string> = {
    africa: 'Africa',
    asia: 'Asia',
    europe: 'Europe',
    'north-america': 'North America',
    'south-america': 'South America',
    oceania: 'Oceania',
}

const Index = ({ spotGuides, weatherData, meta }: Props) => {
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

    const mastheadImage = spotGuides.find((s) => s.thumbnail)?.thumbnail || ''

    return (
        <Layout title={meta.title} description={meta.description}>

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

            {/* ─── Map ─── */}
            <DestinationsMap spotGuides={spotGuides} />

            {/* ─── Continent sections ─── */}
            {Object.entries(groupedByContinent).map(([continent, guides], sectionIndex) => (
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
                                <Link
                                    href={`/destinations/${guide.slug}`}
                                    className="group relative block w-full h-full overflow-hidden bg-primary-darker"
                                >
                                    {guide.thumbnail && (
                                        <img
                                            src={guide.thumbnail}
                                            alt={guide.title}
                                            className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                        />
                                    )}
                                    <div className="absolute inset-0 bg-black/25 group-hover:bg-black/40 transition-colors duration-500" />
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent" />

                                    {/* Card text — bottom-left editorial */}
                                    <div className="absolute bottom-0 left-0 right-0 p-5">
                                        <h3
                                            className="font-display text-white leading-none tracking-wide drop-shadow-lg"
                                            style={{ fontSize: 'clamp(1.3rem, 2.5vw, 1.9rem)' }}
                                        >
                                            {guide.title}
                                        </h3>
                                        {guide.country && (
                                            <p className="text-white/55 text-[10px] mt-1.5 uppercase tracking-[0.2em]">
                                                {guide.country.name}
                                            </p>
                                        )}
                                        <div className="mt-3 h-px w-0 bg-primary-lighter group-hover:w-10 transition-all duration-500 ease-out" />
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </AnimateInView>

                    {/* Bottom spacing */}
                    <div className="pb-6" />
                </section>
            ))}

            {/* ─── Weather data ─── */}
            {titles.length > 0 && (
                <section className="bg-secondary">
                    {/* Section header */}
                    <div className="container mx-auto pt-16 lg:pt-20 pb-10 lg:pb-12">
                        <div className="flex items-start gap-4">
                            <div className="mt-2 w-1 h-12 bg-orange rounded-full shrink-0" />
                            <div>
                                <h2
                                    className="font-display text-white leading-none tracking-wide"
                                    style={{ fontSize: 'clamp(2.5rem, 5vw, 4.5rem)' }}
                                >
                                    Wind & Weather Data
                                </h2>
                                <p className="text-white/35 text-sm mt-2">
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
