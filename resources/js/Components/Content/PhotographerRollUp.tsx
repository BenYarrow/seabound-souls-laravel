/**
 * PhotographerRollUp — content block listing photographers with a live page:
 * a portrait, name and short bio per card, linking to their profile. Mirrors
 * ContributorRollUp so the About page reads as one system.
 */
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface Photographer {
    name: string
    slug: string
    bio: string | null
    thumbnail: FocalImage | null
}

interface Props {
    heading?: string
    intro?: string
    photographers: Photographer[]
}

const PhotographerRollUp = ({ heading, intro, photographers }: Props) => {
    // Defense-in-depth: the server already excludes slugless records, but never
    // render a `/photographers/null` link if one slips through.
    const validPhotographers = (photographers ?? []).filter((photographer) => photographer.slug)

    if (validPhotographers.length === 0) return null

    return (
        <div className="py-4">
            {heading && (
                <h2
                    className="font-title text-secondary uppercase text-center"
                    style={{ fontSize: 'clamp(1.75rem, 4vw, 2.75rem)' }}
                >
                    {heading}
                </h2>
            )}
            {intro && <p className="text-center text-secondary/60 mt-3 max-w-2xl mx-auto">{intro}</p>}

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mt-10">
                {validPhotographers.map((photographer) => (
                    <Link
                        key={photographer.slug}
                        href={`/photographers/${photographer.slug}`}
                        className="group flex flex-col items-center text-center"
                    >
                        <div className="w-32 h-32 rounded-full overflow-hidden ring-4 ring-cream shadow-lg group-hover:shadow-xl transition-shadow duration-300">
                            {photographer.thumbnail ? (
                                <CoverImage
                                    image={photographer.thumbnail}
                                    alt={photographer.name}
                                    className="w-full h-full"
                                    imageClassName="group-hover:scale-105 transition-transform duration-500"
                                    showCredit={false}
                                />
                            ) : (
                                <div className="w-full h-full bg-primary-lighter" />
                            )}
                        </div>
                        <h3
                            className="font-title text-secondary uppercase mt-4 group-hover:text-primary transition-colors duration-200"
                            style={{ fontSize: 'clamp(1.1rem, 2vw, 1.35rem)' }}
                        >
                            {photographer.name}
                        </h3>
                        {photographer.bio && (
                            <p className="text-sm text-secondary/70 mt-2 max-w-xs">{photographer.bio}</p>
                        )}
                    </Link>
                ))}
            </div>
        </div>
    )
}

export default PhotographerRollUp
