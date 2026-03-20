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

    const invertText = backgroundColour !== 'bg-white' && backgroundColour !== 'bg-primary-lightest'
    const headingClasses = `uppercase text-3xl lg:text-4xl font-bold ${invertText ? 'text-primary-lighter' : 'text-primary'}`

    return (
        <BlockWrapper options={{ noContainer: true, fill: true, bgColourClass: backgroundColour }}>
            <div className="space-y-8 lg:space-y-12">
                <div className="container mx-auto flex items-center justify-between">
                    <h2 className={headingClasses}>{title}</h2>
                    <Button href={linkHref}>
                        {linkLabel}
                        {linkScreenReaderLabel && <span className="sr-only">{linkScreenReaderLabel}</span>}
                    </Button>
                </div>

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
                                        className="absolute inset-0 w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                    />
                                ) : (
                                    <div className="absolute inset-0 bg-primary" />
                                )}
                                <div className="absolute inset-0 bg-black/30 group-hover:bg-black/40 transition-colors duration-300" />
                                <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-4">
                                    <h3 className="text-white text-2xl lg:text-3xl font-bold drop-shadow-lg">
                                        {entry.title}
                                    </h3>
                                    {entry.subtitle && (
                                        <p className="text-white/80 text-sm mt-2 uppercase tracking-wider">
                                            {entry.subtitle}
                                        </p>
                                    )}
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
