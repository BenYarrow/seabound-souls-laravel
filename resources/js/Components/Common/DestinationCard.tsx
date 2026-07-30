/**
 * DestinationCard — the aspect-square editorial spot-guide card.
 *
 * Shared by the destinations listing and the contributor profile page so both
 * grids look identical. The parent supplies the aspect ratio (wrap in an
 * `aspect-square` element); this component fills it. The optional `byline` is
 * hidden when null — e.g. on a contributor's own profile, where every card is
 * already known to be theirs.
 */
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface DestinationCardProps {
    title: string
    slug: string
    thumbnail: FocalImage | null
    countryName?: string | null
    /** Attribution line (e.g. "By Marina Reef" / "Seabound Sessions"); null hides it. */
    byline?: string | null
    /** e.g. "≈ 19 days ≥ 20 kts" — the sailable-days figure for the active filter; null hides it. */
    stat?: string | null
    /** Continent label shown in the flat "global" ranking so region is still visible; null hides it. */
    continentLabel?: string | null
}

/**
 * Render a single spot-guide card linking to its destination page.
 */
const DestinationCard = ({ title, slug, thumbnail, countryName, byline, stat, continentLabel }: DestinationCardProps) => (
    <Link
        href={`/destinations/${slug}`}
        className="group relative block w-full h-full overflow-hidden bg-primary-darker"
    >
        {thumbnail && (
            <CoverImage
                image={thumbnail}
                alt={title}
                className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-700 ease-out"
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
                {title}
            </h3>
            {stat && (
                <p className="text-primary-lighter text-[11px] mt-1 font-medium tracking-wide tabular-nums">
                    {stat}
                </p>
            )}
            {(countryName || continentLabel) && (
                <p className="text-white/55 text-[10px] mt-1.5 uppercase tracking-[0.2em]">
                    {[countryName, continentLabel].filter(Boolean).join(' · ')}
                </p>
            )}
            {byline && <p className="text-white/75 text-[11px] mt-2 italic">{byline}</p>}
            <div className="mt-3 h-px w-0 bg-primary-lighter group-hover:w-10 transition-all duration-500 ease-out" />
        </div>
    </Link>
)

export default DestinationCard
