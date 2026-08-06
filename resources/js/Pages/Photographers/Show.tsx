/**
 * Photographers/Show — a public photographer profile: masthead, portrait, bio,
 * socials and their content-builder page body. Deliberately mirrors
 * Contributors/Show so the two profile types read as one system.
 */
import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import ContentBuilder from '@/Components/ContentBuilder'
import CoverImage from '@/Components/Common/CoverImage'
import SocialLinks from '@/Components/Common/SocialLinks'
import type { FocalImage } from '@/types/media'

interface Props {
    photographer: {
        name: string
        bio: string | null
        thumbnail: FocalImage | null
        socials: Record<string, string>
        profile_blocks: any[]
    }
    static_masthead: FocalImage | null
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

/**
 * Render a photographer's public profile page.
 *
 * @param photographer the resolved photographer payload (name, bio, portrait, socials, profile content)
 * @param static_masthead the hero image for the top of the page, or null for the gradient fallback
 * @param meta page title/description/keywords/og_image for the document head
 */
const Show = ({ photographer, static_masthead, meta }: Props) => (
    <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
        <StaticMasthead imageUrl={static_masthead} eyebrow="Photographer" title={photographer.name} />

        <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>
            <div className="flex flex-col items-center text-center">
                {photographer.thumbnail && (
                    <div className="w-40 h-40 md:w-48 md:h-48 rounded-full overflow-hidden ring-4 ring-white shadow-xl">
                        {/* Their own portrait needs no credit badge over it. */}
                        <CoverImage
                            image={photographer.thumbnail}
                            alt={photographer.name}
                            className="w-full h-full"
                            showCredit={false}
                        />
                    </div>
                )}

                {photographer.bio && (
                    <p className="mt-6 max-w-2xl text-secondary/80 leading-relaxed">{photographer.bio}</p>
                )}

                <SocialLinks socials={photographer.socials} className="mt-6 justify-center" />
            </div>

            {photographer.profile_blocks?.length > 0 && (
                <div className="mt-12">
                    <ContentBuilder blocks={photographer.profile_blocks} />
                </div>
            )}
        </BlockWrapper>
    </Layout>
)

export default Show
