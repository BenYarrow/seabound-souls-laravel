// resources/js/Pages/Destinations/Index.tsx
//
// Destinations index page. Ranks every published spot by its typical number of
// sailable days for the chosen month + minimum wind, laid out global/continent/
// country, with the ranking + charts driven entirely client-side from props the
// controller already shipped. Filter state is mirrored to the URL (no Inertia
// visit) so a filtered view is shareable.

import { useMemo, useState } from 'react'
import { groupBy } from 'lodash'

import Layout from '@/Layouts/Layout'
import DestinationCard from '@/Components/Common/DestinationCard'
import FeaturedHero from '@/Components/Common/FeaturedHero'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import DestinationsMap from '@/Components/Map/DestinationsMap'
import DestinationFilterBar from '@/Components/Destinations/DestinationFilterBar'
import SailableDaysChart from '@/Components/Destinations/SailableDaysChart'
import AllDestinationsWindChart from '@/Components/Destinations/AllDestinationsWindChart'
import AllDestinationsTempChart from '@/Components/Destinations/AllDestinationsTempChart'
import AnimateInView from '@/Components/Common/AnimateInView'
import { getSpotGuideColours } from '@/Helpers/colours'
import { rankSpots, unitToKts, type SailableDataset } from '@/Helpers/sailableDays'
import { parseFilters, filtersToQuery, type DestinationFilters, type GroupBy } from '@/Helpers/destinationFilters'
import { MONTH_NAMES, type ClimateDataset } from '@/Helpers/climate'
import type { SelectOption } from '@/Helpers/selectTypes'
import type { FocalImage } from '@/types/media'

/** Author attribution: 'house' (us) shows the brand; 'contributor' shows the name (and links to their profile). */
interface Author { kind: 'house' | 'contributor'; name: string | null; slug: string | null }

interface SpotGuide {
    id: number
    title: string
    slug: string
    latitude: number | null
    longitude: number | null
    country: { name: string; slug: string; continent: string } | null
    thumbnail: FocalImage | null
    author: Author
}

interface Props {
    spotGuides: SpotGuide[]
    sailableDays: SailableDataset
    climate: ClimateDataset
    showProvenance: boolean
    static_masthead: FocalImage | null
    featuredSpotGuide: { id: number; title: string; slug: string; country: string | null; thumbnail: FocalImage | null } | null
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const CONTINENT_LABELS: Record<string, string> = {
    africa: 'Africa', asia: 'Asia', europe: 'Europe',
    'north-america': 'North America', 'south-america': 'South America', oceania: 'Oceania',
}

/**
 * Destinations index: rank spots by typical sailable days for the chosen month +
 * minimum wind, grouped by continent/country/global, with URL-synced filters and
 * the sailable-days / wind / temperature charts below.
 */
const Index = ({ spotGuides, sailableDays, climate, showProvenance, static_masthead, featuredSpotGuide, meta }: Props) => {
    // The ranking universe is EVERY published spot — not only those with weather
    // data. A spot missing from `sailableDays` ranks 0 and sinks to the bottom
    // (per the spec's "zero-day spots stay visible" rule), so it never vanishes.
    const allTitles = spotGuides.map((guide) => guide.title)
    // Colours are keyed by title and built from the full set so a spot's colour is
    // stable regardless of which spots are selected. `climate` (chart series only)
    // must NOT seed the ranking — that is what silently dropped dataless spots.
    const colours = useMemo(() => getSpotGuideColours(allTitles), [allTitles])

    // Multiselect options carry the title as label and the SLUG as value — slugs
    // are what we serialise to the URL (titles contain spaces/commas that would
    // break the comma-join).
    const destinationOptions: SelectOption[] = spotGuides.map((guide) => ({ label: guide.title, value: guide.slug }))
    // Resolve URL slugs back to titles (ranking + charts are keyed by title).
    const slugToTitle = useMemo(() => {
        const lookup: Record<string, string> = {}
        spotGuides.forEach((guide) => { lookup[guide.slug] = guide.title })
        return lookup
    }, [spotGuides])

    const currentMonth = new Date().getMonth() + 1
    // Lazy initialiser: parse the URL exactly once on mount. SSR is not enabled in
    // this project, so `window` is always defined here.
    const [filters, setFilters] = useState<DestinationFilters>(
        () => parseFilters(window.location.search, { month: currentMonth })
    )

    /** Apply a filter change: update local state and mirror it to the URL. */
    const updateFilters = (next: DestinationFilters) => {
        setFilters(next)
        const query = new URLSearchParams(filtersToQuery(next)).toString()
        // Mirror to the URL without an Inertia visit — no server round-trip, and
        // Inertia's history state object is preserved (passed straight back).
        window.history.replaceState(window.history.state, '', `/destinations?${query}`)
    }

    const monthOptions = MONTH_NAMES.map((name, index) => ({ label: name, value: index + 1 }))
    const groupOptions: { label: string; value: GroupBy }[] = [
        { label: 'By continent', value: 'continent' },
        { label: 'By country', value: 'country' },
        { label: 'Global ranking', value: 'global' },
    ]

    const minKts = unitToKts(filters.min, filters.unit)
    // Selected slugs → titles (dropping any unknown/stale slug from a shared link);
    // empty selection = the whole published set.
    const activeTitles = filters.spots.length > 0
        ? filters.spots.map((slug) => slugToTitle[slug]).filter(Boolean)
        : allTitles
    const ranked = useMemo(
        () => rankSpots(sailableDays, activeTitles, filters.month, minKts),
        [sailableDays, activeTitles, filters.month, minKts]
    )

    // Rank order as a lookup so card grids can sort by it.
    const rankIndex = useMemo(() => {
        const lookup: Record<string, number> = {}
        ranked.forEach((row, index) => { lookup[row.title] = index })
        return lookup
    }, [ranked])

    const spotByTitle = useMemo(() => {
        const lookup: Record<string, SpotGuide> = {}
        spotGuides.forEach((guide) => { lookup[guide.title] = guide })
        return lookup
    }, [spotGuides])

    /** "≈ N days ≥ X unit" stat for a card, from the ranked row. */
    const statFor = (title: string): string => {
        const row = ranked.find((entry) => entry.title === title)
        const days = row ? Math.round(row.avgDaysThisMonth) : 0
        return `≈ ${days} ${days === 1 ? 'day' : 'days'} ≥ ${filters.min} ${filters.unit}`
    }

    // `ranked` covers every active title (all published spots when nothing is
    // selected), so every spot — dataless ones included — appears in the grid.
    const rankedGuides = ranked.map((row) => spotByTitle[row.title]).filter(Boolean) as SpotGuide[]

    const mastheadImage = static_masthead ?? spotGuides.find((s) => s.thumbnail)?.thumbnail ?? null
    const minLabel = `${filters.min} ${filters.unit}`

    /** Build the grouped sections for continent/country grouping, each rank-sorted, groups ordered by their best spot. */
    const buildGroups = (key: 'continent' | 'country') => {
        const grouped = groupBy(
            rankedGuides.filter((guide) => guide.country),
            (guide) => key === 'continent' ? guide.country!.continent : guide.country!.slug
        )
        const entries = Object.entries(grouped)
        // Order groups by their best-ranked member (lowest rankIndex) — the region
        // holding your #1 spot leads. CONTINENT_LABELS is used only for labels now.
        entries.sort(([, first], [, second]) =>
            Math.min(...first.map((guide) => rankIndex[guide.title])) -
            Math.min(...second.map((guide) => rankIndex[guide.title]))
        )
        return entries
    }

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            <StaticMasthead imageUrl={mastheadImage} title="Destinations" eyebrow="Windsurfing around the world" />

            {/* Filter bar — drives ranking + charts, synced to the URL */}
            <DestinationFilterBar
                monthOptions={monthOptions}
                groupOptions={groupOptions}
                destinationOptions={destinationOptions}
                filters={filters}
                onChange={updateFilters}
            />

            {/* Intro */}
            <section className="bg-cream">
                <div className="container mx-auto py-12 lg:py-16">
                    <span className="block w-8 h-0.5 bg-orange mb-5" />
                    <h2 className="font-display text-secondary leading-none tracking-wide" style={{ fontSize: 'clamp(2.2rem, 5vw, 4rem)' }}>
                        Where's Windy in {MONTH_NAMES[filters.month - 1]}?
                    </h2>
                    <p className="text-gray-500 text-base lg:text-lg leading-relaxed mt-4 max-w-2xl">
                        Ranked by the typical number of days each spot blows {filters.min} {filters.unit} or more
                        for at least two hours — set your minimum above.
                    </p>
                </div>
            </section>

            {featuredSpotGuide && (
                <section className="bg-white">
                    <div className="container mx-auto py-14 lg:py-16">
                        <FeaturedHero image={featuredSpotGuide.thumbnail} eyebrow="Featured Destination" title={featuredSpotGuide.title} metaLabel={featuredSpotGuide.country} href={`/destinations/${featuredSpotGuide.slug}`} ctaLabel="Explore guide" />
                    </div>
                </section>
            )}

            <DestinationsMap spotGuides={spotGuides} />

            {/* Card layouts */}
            {filters.group === 'global' ? (
                <section className="bg-white">
                    <div className="container mx-auto pt-14 lg:pt-18">
                        <SectionHeading label={`Best for ${MONTH_NAMES[filters.month - 1]}`} count={rankedGuides.length} />
                    </div>
                    <CardGrid guides={rankedGuides} showProvenance={showProvenance} statFor={statFor} withContinent />
                    <div className="container mx-auto pb-6" />
                </section>
            ) : (
                buildGroups(filters.group).map(([groupKey, guides], sectionIndex) => (
                    <section key={groupKey} className={sectionIndex % 2 === 0 ? 'bg-white' : 'bg-cream'}>
                        <div className="container mx-auto pt-14 lg:pt-18">
                            <SectionHeading
                                label={filters.group === 'continent' ? (CONTINENT_LABELS[groupKey] || groupKey) : (guides[0]?.country?.name || groupKey)}
                                count={guides.length}
                            />
                        </div>
                        <CardGrid guides={guides} showProvenance={showProvenance} statFor={statFor} />
                        <div className="container mx-auto pb-6" />
                    </section>
                ))
            )}

            {/* Charts */}
            {allTitles.length > 0 && (
                <section className="bg-primary-lightest">
                    <div className="container mx-auto pt-16 lg:pt-20 pb-10 lg:pb-12">
                        <div className="flex items-start gap-4">
                            <div className="mt-2 w-1 h-12 bg-orange rounded-full shrink-0" />
                            <div>
                                <h2 className="font-display text-secondary leading-none tracking-wide" style={{ fontSize: 'clamp(2.5rem, 5vw, 4.5rem)' }}>Wind & Weather Data</h2>
                                <p className="text-secondary/50 text-sm mt-2">Typical-year averages across all destinations · {MONTH_NAMES[filters.month - 1]} highlighted</p>
                            </div>
                        </div>
                    </div>
                    <div className="container mx-auto py-4 lg:py-8 space-y-8">
                        <SailableDaysChart ranked={ranked} colours={colours} selectedMonth={filters.month} minLabel={minLabel} />
                        <AllDestinationsWindChart
                            climate={climate}
                            activeDestinations={activeTitles.map((title) => ({ label: title, value: title }))}
                            activeWindUnit={filters.unit}
                            colours={colours}
                            selectedMonth={filters.month}
                        />
                        <AllDestinationsTempChart climate={climate} activeDestinations={activeTitles.map((title) => ({ label: title, value: title }))} colours={colours} selectedMonth={filters.month} />
                    </div>
                </section>
            )}
        </Layout>
    )
}

/** Continent/country/global section heading. */
const SectionHeading = ({ label, count }: { label: string; count: number }) => (
    <div className="flex items-center gap-5 mb-10 lg:mb-12">
        <div className="flex items-start gap-4">
            <div className="mt-2 w-1 h-10 bg-orange rounded-full shrink-0" />
            <h2 className="font-display text-secondary leading-none tracking-wide" style={{ fontSize: 'clamp(2.2rem, 5vw, 4.5rem)' }}>{label}</h2>
        </div>
        <div className="flex-1 h-px bg-gradient-to-r from-secondary/15 to-transparent hidden md:block" />
        <span className="text-secondary/30 text-sm font-medium tabular-nums hidden md:block">{count} {count === 1 ? 'spot' : 'spots'}</span>
    </div>
)

/** Rank-ordered card grid. */
const CardGrid = ({ guides, showProvenance, statFor, withContinent = false }: {
    guides: SpotGuide[]; showProvenance: boolean; statFor: (title: string) => string; withContinent?: boolean
}) => (
    <AnimateInView tag="ul" animateChildren classes="container mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
        {guides.map((guide) => (
            <li key={guide.id} className="aspect-square">
                <DestinationCard
                    title={guide.title}
                    slug={guide.slug}
                    thumbnail={guide.thumbnail}
                    countryName={guide.country?.name}
                    continentLabel={withContinent ? (CONTINENT_LABELS[guide.country?.continent ?? ''] ?? null) : null}
                    stat={statFor(guide.title)}
                    byline={showProvenance ? (guide.author.kind === 'contributor' && guide.author.name ? `By ${guide.author.name}` : 'Seabound Souls') : null}
                />
            </li>
        ))}
    </AnimateInView>
)

export default Index
