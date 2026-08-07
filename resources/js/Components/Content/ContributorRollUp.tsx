/**
 * ContributorRollUp — content block rendering the crew: a card per contributor
 * with a public profile (portrait + name + guide count), linking to their page.
 */
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface Contributor {
    name: string
    slug: string
    profile_image: FocalImage | null
    guides_count: number
}

interface Props {
    heading?: string
    intro?: string
    contributors: Contributor[]
}

/** Pluralise the guide count for a crew card. */
const guideLabel = (count: number): string => `${count} ${count === 1 ? 'guide' : 'guides'}`

const ContributorRollUp = ({ heading, intro, contributors }: Props) => {
    // Defense-in-depth: the server already excludes slugless contributors, but
    // never render a `/contributors/null` link if one slips through.
    const validContributors = (contributors ?? []).filter((contributor) => contributor.slug)

    if (validContributors.length === 0) return null

    return (
        <div className="py-4">
            {heading && (
                <h2 className="font-title text-secondary uppercase text-center" style={{ fontSize: 'clamp(1.75rem, 4vw, 2.75rem)' }}>
                    {heading}
                </h2>
            )}
            {intro && <p className="text-center text-secondary/60 mt-3 max-w-2xl mx-auto">{intro}</p>}

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8 mt-10">
                {validContributors.map((contributor) => (
                    <Link key={contributor.slug} href={`/contributors/${contributor.slug}`} className="group flex flex-col items-center text-center">
                        <div className="w-32 h-32 rounded-full overflow-hidden ring-4 ring-white shadow-lg group-hover:shadow-xl transition-shadow duration-300">
                            {contributor.profile_image ? (
                                <CoverImage
                                    image={contributor.profile_image}
                                    alt={contributor.name}
                                    className="w-full h-full"
                                    imageClassName="group-hover:scale-105 transition-transform duration-500"
                                    showCredit={false}
                                />
                            ) : (
                                <div className="w-full h-full bg-primary-lighter" />
                            )}
                        </div>
                        <h3 className="font-title text-secondary uppercase mt-4 group-hover:text-primary transition-colors duration-200" style={{ fontSize: 'clamp(1.1rem, 2vw, 1.35rem)' }}>
                            {contributor.name}
                        </h3>
                        <span className="text-[11px] uppercase tracking-[0.3em] text-primary/60 mt-1">{guideLabel(contributor.guides_count)}</span>
                    </Link>
                ))}
            </div>
        </div>
    )
}

export default ContributorRollUp
