/**
 * Contributors/Show — a public contributor profile: masthead (name), portrait +
 * socials, their content-builder story, and a grid of their published guides
 * (reusing the shared DestinationCard so it matches the destinations page).
 */
import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import ContentBuilder from '@/Components/ContentBuilder'
import CoverImage from '@/Components/Common/CoverImage'
import DestinationCard from '@/Components/Common/DestinationCard'
import SocialLinks from '@/Components/Common/SocialLinks'
import type { FocalImage } from '@/types/media'

interface Guide {
    id: number
    title: string
    slug: string
    thumbnail: FocalImage | null
    country: string | null
}

interface Props {
    contributor: {
        name: string
        first_name: string | null
        profile_image: FocalImage | null
        socials: Record<string, string>
        profile_blocks: any[]
    }
    static_masthead: FocalImage | null
    guides: Guide[]
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const Show = ({ contributor, static_masthead, guides, meta }: Props) => {
    // The masthead already shows the full name, so the guides heading uses the
    // first name only ("Guides by Ben") for a friendlier, non-repetitive read.
    const firstName = contributor.first_name || contributor.name

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            <StaticMasthead imageUrl={static_masthead} eyebrow="Contributor" title={contributor.name} />

            {/* Intro band — portrait + socials (name lives in the masthead above) */}
            <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>
                <div className="flex flex-col items-center text-center">
                    {contributor.profile_image && (
                        <div className="w-40 h-40 md:w-48 md:h-48 rounded-full overflow-hidden ring-4 ring-white shadow-xl">
                            {/* Circular avatar — a badge would be illegible here (see CoverImage). */}
                            <CoverImage image={contributor.profile_image} alt={contributor.name} className="w-full h-full" showCredit={false} />
                        </div>
                    )}
                    <SocialLinks socials={contributor.socials} className="mt-6 justify-center" />
                </div>

                {/* Their story */}
                {contributor.profile_blocks?.length > 0 && (
                    <div className="mt-12">
                        <ContentBuilder blocks={contributor.profile_blocks} />
                    </div>
                )}
            </BlockWrapper>

            {/* Their guides — mirrors the destinations section exactly (same
                `container mx-auto` heading + grid) so the card width lines up. */}
            {guides.length > 0 && (
                <section className="bg-white">
                    <div className="container mx-auto pt-14 lg:pt-18 pb-0">
                        <h2
                            className="font-title text-secondary uppercase text-center mb-10 lg:mb-12"
                            style={{ fontSize: 'clamp(1.5rem, 3vw, 2.25rem)' }}
                        >
                            Guides by {firstName}
                        </h2>
                    </div>
                    <div className="container mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 pb-14 lg:pb-18">
                        {guides.map((guide) => (
                            <div key={guide.id} className="aspect-square">
                                {/* Byline hidden — every card here is already known to be theirs. */}
                                <DestinationCard
                                    title={guide.title}
                                    slug={guide.slug}
                                    thumbnail={guide.thumbnail}
                                    countryName={guide.country}
                                    byline={null}
                                />
                            </div>
                        ))}
                    </div>
                </section>
            )}
        </Layout>
    )
}

export default Show
