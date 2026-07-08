import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import AnimateInView from '@/Components/Common/AnimateInView'
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'

interface Blog {
    id: number
    title: string
    slug: string
    published_at: string | null
    thumbnail: string
    seo_description: string | null
}

interface Props {
    blogs: {
        data: Blog[]
        links: any[]
        meta: any
    }
    static_masthead: string
    meta: { title: string; description: string }
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

const Index = ({ blogs, static_masthead, meta }: Props) => {
    const [featured, ...rest] = blogs.data

    return (
        <Layout title={meta.title} description={meta.description}>
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

                {/* Featured post */}
                {featured && (
                    <AnimateInView classes="mb-12 md:mb-16" outViewClasses="translate-y-8 opacity-0" delayClasses="delay-0" durationClasses="duration-700">
                        <Link
                            href={`/blog/${featured.slug}`}
                            className="group block md:grid md:grid-cols-5 rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-500 bg-white"
                        >
                            {/* Image */}
                            <div className="md:col-span-3 aspect-[16/10] md:aspect-auto overflow-hidden relative min-h-[280px]">
                                {featured.thumbnail ? (
                                    <CoverImage
                                        image={featured.thumbnail}
                                        alt={featured.title}
                                        className="w-full h-full group-hover:scale-105 transition-transform duration-700"
                                    />
                                ) : (
                                    <div className="w-full h-full bg-primary-lighter" />
                                )}
                                <div className="absolute inset-0 bg-gradient-to-r from-transparent to-black/15 hidden md:block pointer-events-none" />
                            </div>

                            {/* Content */}
                            <div className="md:col-span-2 flex flex-col justify-center p-8 md:p-10 lg:p-14 border-l-[3px] border-l-transparent group-hover:border-l-primary transition-all duration-500">
                                <span className="text-[10px] uppercase tracking-[0.4em] text-orange font-semibold mb-4">
                                    Featured
                                </span>
                                <h2
                                    className="font-title text-secondary uppercase leading-[1.0] group-hover:text-primary transition-colors duration-300"
                                    style={{ fontSize: 'clamp(1.75rem, 3vw, 2.75rem)' }}
                                >
                                    {featured.title}
                                </h2>
                                {featured.seo_description && (
                                    <p className="text-gray-500 mt-5 text-sm leading-relaxed line-clamp-3">
                                        {featured.seo_description}
                                    </p>
                                )}
                                {featured.published_at && (
                                    <p className="text-primary/60 text-[10px] uppercase tracking-[0.35em] mt-6">
                                        {formatDate(featured.published_at)}
                                    </p>
                                )}
                                <span className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary group-hover:gap-4 transition-all duration-300">
                                    Read article <ArrowIcon className="w-4 h-4" />
                                </span>
                            </div>
                        </Link>
                    </AnimateInView>
                )}

                {/* Article grid */}
                {rest.length > 0 && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        {rest.map((blog, i) => (
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
