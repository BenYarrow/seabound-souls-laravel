import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import AnimateInView from '@/Components/Common/AnimateInView'
import FeaturedHero from '@/Components/Common/FeaturedHero'
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface Blog {
    id: number
    title: string
    slug: string
    published_at: string | null
    /** Focal-bearing image object (or null when no thumbnail). */
    thumbnail: FocalImage | null
    seo_description: string | null
}

interface Props {
    blogs: {
        data: Blog[]
        links: any[]
        meta: any
    }
    /** The owner-flagged post, or null when nothing is flagged (no fallback). */
    featured: Blog | null
    /** Focal-bearing image for the masthead, or null when no page record exists. */
    static_masthead: FocalImage | null
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const formatDate = (dateStr: string | null): string => {
    if (!dateStr) return ''
    return new Date(dateStr).toLocaleDateString('en-GB', { day: 'numeric', month: 'long', year: 'numeric' })
}

const ArrowIcon = ({ className }: { className?: string }) => (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
    </svg>
)

const Index = ({ blogs, featured, static_masthead, meta }: Props) => {
    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            {static_masthead ? (
                <StaticMasthead
                    imageUrl={static_masthead}
                    title="Blog"
                    subtitle="Windsurfing tips, guides and destination insights"
                />
            ) : (
                <div className="bg-primary py-16">
                    <div className="container mx-auto">
                        <h1 className="text-white text-4xl md:text-5xl font-bold">Blog</h1>
                        <p className="text-white opacity-80 text-lg mt-3">Windsurfing tips and destination insights</p>
                    </div>
                </div>
            )}

            <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>

                {/* Section divider */}
                <div className="flex items-center gap-5 mb-12">
                    <div className="h-px flex-1 bg-primary/20" />
                    <span className="text-[10px] uppercase tracking-[0.45em] text-primary font-light shrink-0">Latest Articles</span>
                    <div className="h-px flex-1 bg-primary/20" />
                </div>

                {/* Featured post — owner-flagged, shown on page 1 only */}
                {featured && blogs.meta.current_page === 1 && (
                    <FeaturedHero
                        image={featured.thumbnail}
                        eyebrow="Featured"
                        title={featured.title}
                        description={featured.seo_description}
                        metaLabel={featured.published_at ? formatDate(featured.published_at) : null}
                        href={`/blog/${featured.slug}`}
                        ctaLabel="Read article"
                    />
                )}

                {/* Article grid */}
                {blogs.data.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        {blogs.data.map((blog, i) => (
                            <AnimateInView
                                key={blog.id}
                                outViewClasses="translate-y-8 opacity-0"
                                delayClasses={i % 3 === 0 ? 'delay-0' : i % 3 === 1 ? 'delay-[100ms]' : 'delay-200'}
                                durationClasses="duration-500"
                            >
                                <Link
                                    href={`/blog/${blog.slug}`}
                                    className="group flex flex-col rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300 bg-white h-full"
                                >
                                    {/* Image */}
                                    <div className="relative aspect-[16/10] overflow-hidden shrink-0">
                                        {blog.thumbnail ? (
                                            <CoverImage
                                                image={blog.thumbnail}
                                                alt={blog.title}
                                                className="w-full h-full group-hover:scale-105 transition-transform duration-500"
                                            />
                                        ) : (
                                            <div className="w-full h-full bg-primary-lighter" />
                                        )}
                                        <div className="absolute inset-0 bg-gradient-to-t from-black/25 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none" />
                                    </div>

                                    {/* Content */}
                                    <div className="flex flex-col flex-1 p-5 md:p-6">
                                        {blog.published_at && (
                                            <p className="text-[10px] uppercase tracking-[0.35em] text-primary/60 mb-2">
                                                {formatDate(blog.published_at)}
                                            </p>
                                        )}
                                        <h3
                                            className="font-title text-secondary uppercase leading-[1.05] group-hover:text-primary transition-colors duration-300"
                                            style={{ fontSize: 'clamp(1.1rem, 1.8vw, 1.35rem)' }}
                                        >
                                            {blog.title}
                                        </h3>
                                        {blog.seo_description && (
                                            <p className="text-gray-500 text-sm mt-2.5 line-clamp-2 leading-relaxed flex-1">
                                                {blog.seo_description}
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
                )}

                {/* Empty state */}
                {blogs.data.length === 0 && (
                    <div className="text-center py-20 text-gray-400">
                        <p className="font-title text-2xl uppercase">No articles yet</p>
                        <p className="text-sm mt-2">Check back soon.</p>
                    </div>
                )}

                {/* Pagination */}
                {blogs.meta?.last_page > 1 && (
                    <div className="mt-14 flex justify-center items-center gap-2">
                        {blogs.links.map((link: any, i: number) =>
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

export default Index
