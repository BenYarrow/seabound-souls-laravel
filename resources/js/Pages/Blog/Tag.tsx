/**
 * Blog/Tag — public, crawlable topic-hub page for a single blog tag.
 *
 * Rendered by `TagController@show` at `/blog/tags/{slug}`. Mirrors the blog
 * index's card grid + pagination so tag pages feel like a native section of
 * the blog rather than a bolt-on filter view. See docs/history/ (Blog Tags
 * feature) for the wider design — this file covers only the frontend view.
 */
import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import AnimateInView from '@/Components/Common/AnimateInView'
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

/** A post card as projected by TagController@show (same shape as the blog index). */
interface Post {
    id: number
    title: string
    slug: string
    published_at: string | null
    thumbnail: FocalImage | null
    seo_description: string | null
}

interface Props {
    tag: { name: string; description: string | null }
    posts: {
        data: Post[]
        links: any[]
        current_page: number
        last_page: number
    }
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

/**
 * Format an ISO date string as a UK long date, or '' when null.
 *
 * @param dateStr ISO date string or null
 */
const formatDate = (dateStr: string | null): string => {
    if (!dateStr) return ''
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

const ArrowIcon = ({ className }: { className?: string }) => (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
    </svg>
)

/**
 * Public tag page — a crawlable topic hub listing every published post carrying
 * one tag, with optional intro copy above the grid.
 */
const Tag = ({ tag, posts, meta }: Props) => {
    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            <div className="bg-primary py-16">
                <div className="container mx-auto">
                    <p className="text-white/70 text-[10px] uppercase tracking-[0.45em] mb-3">Tagged</p>
                    <h1 className="text-white text-4xl md:text-5xl font-bold">{tag.name}</h1>
                    {tag.description && (
                        <p className="text-white/80 text-lg mt-4 max-w-3xl leading-relaxed">{tag.description}</p>
                    )}
                </div>
            </div>

            <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>
                <div className="mb-10">
                    <Link href="/blog" className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:gap-3 transition-all duration-300">
                        <span className="rotate-180"><ArrowIcon className="w-3 h-3" /></span> All articles
                    </Link>
                </div>

                {posts.data.length > 0 ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        {posts.data.map((post, i) => (
                            <AnimateInView
                                key={post.id}
                                outViewClasses="translate-y-8 opacity-0"
                                delayClasses={i % 3 === 0 ? 'delay-0' : i % 3 === 1 ? 'delay-[100ms]' : 'delay-200'}
                                durationClasses="duration-500"
                            >
                                <Link
                                    href={`/blog/${post.slug}`}
                                    className="group flex flex-col rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300 bg-white h-full"
                                >
                                    <div className="relative aspect-[16/10] overflow-hidden shrink-0">
                                        {post.thumbnail ? (
                                            <CoverImage
                                                image={post.thumbnail}
                                                alt={post.title}
                                                className="w-full h-full group-hover:scale-105 transition-transform duration-500"
                                            />
                                        ) : (
                                            <div className="w-full h-full bg-primary-lighter" />
                                        )}
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/25 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none" />
                                    </div>
                                    <div className="flex flex-col flex-1 p-5 md:p-6">
                                        {post.published_at && (
                                            <p className="text-[10px] uppercase tracking-[0.35em] text-primary/60 mb-2">
                                                {formatDate(post.published_at)}
                                            </p>
                                        )}
                                        <h3
                                            className="font-title text-secondary uppercase leading-[1.05] group-hover:text-primary transition-colors duration-300"
                                            style={{ fontSize: 'clamp(1.1rem, 1.8vw, 1.35rem)' }}
                                        >
                                            {post.title}
                                        </h3>
                                        {post.seo_description && (
                                            <p className="text-gray-500 text-sm mt-2.5 line-clamp-2 leading-relaxed flex-1">
                                                {post.seo_description}
                                            </p>
                                        )}
                                        <span className="mt-5 inline-flex items-center gap-1.5 text-xs font-semibold text-primary group-hover:gap-3 transition-all duration-300">
                                            Read more <ArrowIcon className="w-3 h-3" />
                                        </span>
                                    </div>
                                </Link>
                            </AnimateInView>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-20 text-gray-400">
                        <p className="font-title text-2xl uppercase">No articles yet</p>
                    </div>
                )}

                {posts.last_page > 1 && (
                    <div className="mt-14 flex justify-center items-center gap-2">
                        {posts.links.map((link: any, i: number) =>
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`px-4 py-2 rounded text-sm font-medium transition-colors duration-200 ${
                                        link.active
                                            ? 'bg-primary text-white shadow-sm'
                                            : 'border border-primary/30 text-primary hover:bg-primary hover:text-white'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="px-4 py-2 rounded text-sm text-gray-300 border border-gray-200 cursor-not-allowed"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )
                        )}
                    </div>
                )}
            </BlockWrapper>
        </Layout>
    )
}

export default Tag
