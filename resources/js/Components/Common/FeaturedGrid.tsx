import { Link } from '@inertiajs/react'
import BlockWrapper from './BlockWrapper'
import AnimateInView from './AnimateInView'
import Button from './Button'

interface FeaturedGridEntry {
    id: number
    title: string
    slug: string
    thumbnail: string
    subtitle?: string
}

interface FeaturedGridProps {
    title: string
    entries: FeaturedGridEntry[]
    linkHref: string
    linkLabel: string
    linkScreenReaderLabel?: string
    backgroundColour?: string
    buildHref: (entry: FeaturedGridEntry) => string
}

const FeaturedGrid = ({
    title,
    entries,
    linkHref,
    linkLabel,
    linkScreenReaderLabel,
    backgroundColour = 'bg-secondary',
    buildHref,
}: FeaturedGridProps) => {
    if (!entries || entries.length === 0) return null

    const isDark = backgroundColour !== 'bg-white' && backgroundColour !== 'bg-primary-lightest' && backgroundColour !== 'bg-cream'

    return (
        <BlockWrapper options={{ noContainer: true, fill: true, bgColourClass: backgroundColour }}>
            <div className="space-y-10 lg:space-y-14">

                {/* Section header */}
                <div className="container mx-auto flex items-end justify-between gap-6">
                    <div className="flex items-start gap-4">
                        {/* Accent bar */}
                        <div className={`mt-3 w-1 self-stretch rounded-full ${isDark ? 'bg-orange' : 'bg-orange'}`} />
                        <h2
                            className={`font-display leading-none tracking-wide ${isDark ? 'text-white' : 'text-secondary'}`}
                            style={{ fontSize: 'clamp(2.5rem, 6vw, 5.5rem)' }}
                        >
                            {title}
                        </h2>
                    </div>
                    <Button href={linkHref} variant={isDark ? 'outline' : 'primary'}>
                        {linkLabel}
                        {linkScreenReaderLabel && <span className="sr-only">{linkScreenReaderLabel}</span>}
                    </Button>
                </div>

                {/* Card grid */}
                <AnimateInView
                    tag="ul"
                    animateChildren
                    classes="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 lg:container lg:mx-auto"
                >
                    {entries.map((entry) => (
                        <li key={entry.id} className="aspect-square">
                            <Link
                                href={buildHref(entry)}
                                className="group relative block w-full h-full overflow-hidden"
                            >
                                {entry.thumbnail ? (
                                    <img
                                        src={entry.thumbnail}
                                        alt={entry.title}
                                        className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-700 ease-out"
                                    />
                                ) : (
                                    <div className="absolute inset-0 bg-primary" />
                                )}

                                {/* Subtle vignette */}
                                <div className="absolute inset-0 bg-black/20 group-hover:bg-black/35 transition-colors duration-500" />
                                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent" />

                                {/* Text — positioned at bottom-left for editorial feel */}
                                <div className="absolute bottom-0 left-0 right-0 p-5">
                                    <h3 className="font-display text-white leading-none tracking-wide drop-shadow-lg"
                                        style={{ fontSize: 'clamp(1.4rem, 3vw, 2rem)' }}>
                                        {entry.title}
                                    </h3>
                                    {entry.subtitle && (
                                        <p className="text-white/65 text-xs mt-1.5 uppercase tracking-[0.2em]">
                                            {entry.subtitle}
                                        </p>
                                    )}
                                    {/* Hover reveal line */}
                                    <div className="mt-3 h-px w-0 bg-primary-lighter group-hover:w-12 transition-all duration-500" />
                                </div>
                            </Link>
                        </li>
                    ))}
                </AnimateInView>
            </div>
        </BlockWrapper>
    )
}

export default FeaturedGrid
