import { useMemo, useState } from 'react'
import { Link } from '@inertiajs/react'
import { groupBy } from 'lodash'

import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import DestinationsMap from '@/Components/Map/DestinationsMap'
import FilterDataset, { SelectOption } from '@/Components/Destinations/FilterDataset'
import AllDestinationsWindChart from '@/Components/Destinations/AllDestinationsWindChart'
import AllDestinationsTempChart from '@/Components/Destinations/AllDestinationsTempChart'
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

    // Get a masthead image from the first spot guide that has one
    const mastheadImage = spotGuides.find((s) => s.thumbnail)?.thumbnail || ''

    return (
        <Layout title={meta.title} description={meta.description}>
            <StaticMasthead imageUrl={mastheadImage} title="Destinations" />

            <BlockWrapper>
                <div className="max-w-3xl mx-auto text-center space-y-4">
                    <h2 className="text-2xl lg:text-3xl font-bold text-primary">
                        Explore Windsurfing Destinations Around The World
                    </h2>
                    <p className="text-gray-600 lg:text-lg">
                        Discover the best spots for windsurfing, compare weather conditions, and plan your next adventure.
                    </p>
                </div>
            </BlockWrapper>

            <DestinationsMap spotGuides={spotGuides} />

            {Object.entries(groupedByContinent).map(([continent, guides]) => (
                <BlockWrapper key={continent}>
                    <div className="mb-8 lg:mb-12 flex items-center justify-center gap-x-2 lg:gap-x-4">
                        <span className="w-full h-0.5 bg-gradient-to-r from-transparent to-primary"></span>
                        <h2 className="text-3xl lg:text-4xl font-bold uppercase text-primary whitespace-nowrap">
                            {CONTINENT_LABELS[continent] || continent}
                        </h2>
                        <span className="w-full h-0.5 bg-gradient-to-l from-transparent to-primary"></span>
                    </div>

                    <ul className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-0">
                        {guides.map((guide) => (
                            <li key={guide.id} className="aspect-square">
                                <Link
                                    href={`/destinations/${guide.slug}`}
                                    className="group relative block w-full h-full overflow-hidden"
                                >
                                    {guide.thumbnail && (
                                        <img
                                            src={guide.thumbnail}
                                            alt={guide.title}
                                            className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                        />
                                    )}
                                    <div className="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors duration-300" />
                                    <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-4">
                                        <h3 className="text-white text-2xl lg:text-3xl font-bold drop-shadow-lg">
                                            {guide.title}
                                        </h3>
                                        {guide.country && (
                                            <p className="text-white/80 text-sm mt-2 uppercase tracking-wider">
                                                {guide.country.name}
                                            </p>
                                        )}
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </BlockWrapper>
            ))}

            {titles.length > 0 && (
                <>
                    <FilterDataset
                        yearOptions={yearOptions}
                        destinationOptions={destinationOptions}
                        activeYear={activeYear}
                        setActiveYear={setActiveYear}
                        activeDestinations={activeDestinations}
                        setActiveDestinations={setActiveDestinations}
                        onReset={handleReset}
                    />

                    <BlockWrapper>
                        <div className="space-y-12 py-4">
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
                    </BlockWrapper>
                </>
            )}
        </Layout>
    )
}

export default Index
