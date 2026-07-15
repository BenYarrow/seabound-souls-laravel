/**
 * Blog/Tags — the public tag hub at /blog/tags.
 *
 * Rendered by `TagController@index`. Shows a card for every tag that has
 * published posts (each linking to its own /blog/tags/{slug} page), giving
 * readers a browse-by-topic view and search engines a crawlable hub that links
 * the whole tag cluster. A tag without a thumbnail gets a designed gradient
 * card so the grid stays visually consistent.
 */
import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import AnimateInView from '@/Components/Common/AnimateInView'
import TagMasthead from '@/Components/Masthead/TagMasthead'
import { Link } from '@inertiajs/react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

/** A tag card as projected by TagController@index. */
interface TagCard {
    id: number
    name: string
    slug: string
    description: string | null
    posts_count: number
    thumbnail: FocalImage | null
}

interface Props {
    tags: TagCard[]
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

/**
 * Pluralise the article count for a tag card ("1 article" / "4 articles").
 *
 * @param count number of published posts carrying the tag
 */
const articleLabel = (count: number): string => `${count} ${count === 1 ? 'article' : 'articles'}`

/**
 * Public tag hub — a responsive grid of topic cards.
 */
const Tags = ({ tags, meta }: Props) => {
    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            <TagMasthead
                image={null}
                eyebrow="Browse"
                title="Topics"
                subtitle="Every subject we write about — pick a thread and dive in."
            />

            <BlockWrapper options={{ bgColourClass: 'bg-cream' }}>
                <div className="mb-10">
                    <Link
                        href="/blog"
                        className="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:gap-3 transition-all duration-300"
                    >
                        <span className="rotate-180">
                            <svg className="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
                                <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
                            </svg>
                        </span>{' '}
                        All articles
                    </Link>
                </div>

                {tags.length > 0 ? (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                        {tags.map((tag, i) => (
                            <AnimateInView
                                key={tag.id}
                                outViewClasses="translate-y-8 opacity-0"
                                delayClasses={i % 3 === 0 ? 'delay-0' : i % 3 === 1 ? 'delay-[100ms]' : 'delay-200'}
                                durationClasses="duration-500"
                            >
                                <Link
                                    href={`/blog/tags/${tag.slug}`}
                                    className="group relative flex flex-col justify-end aspect-[16/10] rounded-xl overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300"
                                >
                                    {/* Card background — thumbnail, or the designed gradient fallback. */}
                                    {tag.thumbnail ? (
                                        <CoverImage
                                            image={tag.thumbnail}
                                            alt={tag.name}
                                            className="absolute inset-0 w-full h-full group-hover:scale-105 transition-transform duration-500"
                                        />
                                    ) : (
                                        <div className="absolute inset-0 bg-gradient-to-br from-primary-darker via-primary to-primary-darker">
                                            <div className="absolute -top-1/4 -right-1/4 w-2/3 h-2/3 rounded-full bg-primary-lighter/25 blur-2xl" />
                                            <div className="absolute -bottom-1/3 -left-1/4 w-2/3 h-2/3 rounded-full bg-primary/50 blur-2xl" />
                                        </div>
                                    )}

                                    {/* Readability scrim behind the text. */}
                                    <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />

                                    {/* Card content. */}
                                    <div className="relative z-10 p-5 md:p-6">
                                        <span className="inline-block text-[10px] uppercase tracking-[0.35em] text-primary-lighter mb-2">
                                            {articleLabel(tag.posts_count)}
                                        </span>
                                        <h2
                                            className="font-title text-white uppercase leading-[1.0] drop-shadow-lg"
                                            style={{ fontSize: 'clamp(1.5rem, 3vw, 2.1rem)' }}
                                        >
                                            {tag.name}
                                        </h2>
                                        {tag.description && (
                                            <p className="text-white/75 text-sm mt-2 line-clamp-2 leading-relaxed">
                                                {tag.description}
                                            </p>
                                        )}
                                    </div>
                                </Link>
                            </AnimateInView>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-20 text-gray-400">
                        <p className="font-title text-2xl uppercase">No topics yet</p>
                        <p className="text-sm mt-2">Check back soon.</p>
                    </div>
                )}
            </BlockWrapper>
        </Layout>
    )
}

export default Tags
