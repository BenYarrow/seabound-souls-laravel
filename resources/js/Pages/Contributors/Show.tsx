/**
 * Contributors/Show — a public contributor profile: masthead, portrait, socials,
 * their content-builder story, and a grid of their published guides.
 */
import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import ContentBuilder from '@/Components/ContentBuilder'
import CoverImage from '@/Components/Common/CoverImage'
import SocialLinks from '@/Components/Common/SocialLinks'
import { Link } from '@inertiajs/react'
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
        profile_image: FocalImage | null
        socials: Record<string, string>
        profile_blocks: any[]
    }
    static_masthead: FocalImage | null
    guides: Guide[]
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const Show = ({ contributor, static_masthead, guides, meta }: Props) => {
    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            <StaticMasthead imageUrl={static_masthead} eyebrow="Contributor" title={contributor.name} />

            {/* Intro band — portrait overlaps the masthead's bottom edge */}
            <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>
                <div className="flex flex-col items-center text-center -mt-28 md:-mt-32">
                    {contributor.profile_image && (
                        <div className="w-40 h-40 md:w-48 md:h-48 rounded-full overflow-hidden ring-4 ring-cream shadow-xl">
                            <CoverImage image={contributor.profile_image} alt={contributor.name} className="w-full h-full" />
                        </div>
                    )}
                    <h2 className="font-title text-secondary uppercase mt-5" style={{ fontSize: 'clamp(1.75rem, 4vw, 2.75rem)' }}>
                        {contributor.name}
                    </h2>
                    <SocialLinks socials={contributor.socials} className="mt-4 justify-center" />
                </div>

                {/* Their story */}
                {contributor.profile_blocks?.length > 0 && (
                    <div className="mt-12">
                        <ContentBuilder blocks={contributor.profile_blocks} />
                    </div>
                )}
            </BlockWrapper>

            {/* Their guides */}
            {guides.length > 0 && (
                <BlockWrapper options={{ bgColourClass: 'bg-white' }}>
                    <h3 className="font-title text-secondary uppercase text-center mb-10" style={{ fontSize: 'clamp(1.5rem, 3vw, 2.25rem)' }}>
                        Guides by {contributor.name}
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        {guides.map((guide) => (
                            <Link
                                key={guide.id}
                                href={`/destinations/${guide.slug}`}
                                className="group relative flex flex-col justify-end aspect-[16/10] rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300"
                            >
                                {guide.thumbnail ? (
                                    <CoverImage image={guide.thumbnail} alt={guide.title} className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-500" />
                                ) : (
                                    <div className="absolute inset-0 bg-primary-lighter" />
                                )}
                                <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent" />
                                <div className="relative z-10 p-5">
                                    {guide.country && <span className="text-[10px] uppercase tracking-[0.35em] text-primary-lighter">{guide.country}</span>}
                                    <h4 className="font-title text-white uppercase leading-[1.05]" style={{ fontSize: 'clamp(1.1rem, 2vw, 1.4rem)' }}>{guide.title}</h4>
                                </div>
                            </Link>
                        ))}
                    </div>
                </BlockWrapper>
            )}
        </Layout>
    )
}

export default Show
