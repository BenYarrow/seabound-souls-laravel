import Layout from '@/Layouts/Layout'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import MastheadSlider from '@/Components/Masthead/MastheadSlider'
import ContentBuilder from '@/Components/ContentBuilder'
import { formatDate } from '@/Helpers/helpers'
import { Link } from '@inertiajs/react'
import type { FocalImage } from '@/types/media'

interface Props {
    blog: {
        id: number
        title: string
        slug: string
        content_blocks: any[] | null
        published_at: string | null
        /** Focal-bearing image object (or null when no thumbnail). */
        thumbnail: FocalImage | null
        /** Focal-bearing image object (or null when no masthead). */
        static_masthead: FocalImage | null
        masthead_slider: FocalImage[]
        /** Tags assigned to this post, each linking to its crawlable tag page. */
        tags: { name: string; slug: string }[]
    }
    meta: any
}

const Show = ({ blog, meta }: Props) => {
    const hasSlider = blog.masthead_slider?.length > 0

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            {hasSlider ? (
                <MastheadSlider slides={blog.masthead_slider} title={blog.title} />
            ) : (
                <StaticMasthead
                    imageUrl={blog.static_masthead}
                    title={blog.title}
                    subtitle={blog.published_at ? formatDate(blog.published_at) : undefined}
                />
            )}

            {/*
                Tag chips — StaticMasthead/MastheadSlider render the title/date internally
                and aren't extended with a slot for this, so the chips sit directly below
                the masthead (the closest available position to "under the title/meta")
                rather than inside a shared component neither view passes children into here.
            */}
            {blog.tags && blog.tags.length > 0 && (
                <div className="container mx-auto">
                    <div className="flex flex-wrap gap-2 mt-4">
                        {blog.tags.map((tag) => (
                            <Link
                                key={tag.slug}
                                href={`/blog/tags/${tag.slug}`}
                                className="px-3 py-1 rounded-full text-[11px] font-semibold uppercase tracking-wider bg-primary/10 text-primary hover:bg-primary hover:text-white transition-colors duration-200"
                            >
                                {tag.name}
                            </Link>
                        ))}
                    </div>
                </div>
            )}

            {blog.content_blocks && <ContentBuilder blocks={blog.content_blocks} />}
        </Layout>
    )
}

export default Show
