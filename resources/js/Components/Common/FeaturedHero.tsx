// A large "featured" standout card used at the top of listing pages (blog index,
// destinations). Image on the left, editorial content on the right. Purely
// presentational — the caller decides what is featured and formats the meta line.

import { Link } from '@inertiajs/react'
import AnimateInView from '@/Components/Common/AnimateInView'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface Props {
    /** Focal-bearing image, or null to render a plain brand-colour panel. */
    image: FocalImage | null
    /** Small uppercase kicker above the title, e.g. "Featured". */
    eyebrow: string
    title: string
    /** Optional supporting paragraph (blog description). */
    description?: string | null
    /** Optional small uppercase meta line (blog date, or a guide's country). */
    metaLabel?: string | null
    href: string
    /** Call-to-action text, e.g. "Read article" / "Explore guide". */
    ctaLabel: string
}

/** Right-pointing arrow used in the CTA. */
const ArrowIcon = ({ className }: { className?: string }) => (
    <svg className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
    </svg>
)

/**
 * Render the featured standout card.
 * @param props - See {@link Props}.
 */
const FeaturedHero = ({ image, eyebrow, title, description, metaLabel, href, ctaLabel }: Props) => (
    <AnimateInView classes="mb-12 md:mb-16" outViewClasses="translate-y-8 opacity-0" delayClasses="delay-0" durationClasses="duration-700">
        <Link
            href={href}
            className="group block md:grid md:grid-cols-5 rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-500 bg-white"
        >
            <div className="md:col-span-3 aspect-[16/10] md:aspect-auto overflow-hidden relative min-h-[280px]">
                {image ? (
                    <CoverImage
                        image={image}
                        alt={title}
                        className="w-full h-full"
                        imageClassName="group-hover:scale-105 transition-transform duration-700"
                    />
                ) : (
                    <div className="w-full h-full bg-primary-lighter" />
                )}
                <div className="absolute inset-0 bg-gradient-to-r from-transparent to-black/15 hidden md:block pointer-events-none" />
            </div>

            <div className="md:col-span-2 flex flex-col justify-center p-8 md:p-10 lg:p-14 border-l-[3px] border-l-transparent group-hover:border-l-primary transition-all duration-500">
                <span className="text-[10px] uppercase tracking-[0.4em] text-orange font-semibold mb-4">
                    {eyebrow}
                </span>
                <h2
                    className="font-title text-secondary uppercase leading-[1.0] group-hover:text-primary transition-colors duration-300"
                    style={{ fontSize: 'clamp(1.75rem, 3vw, 2.75rem)' }}
                >
                    {title}
                </h2>
                {description && (
                    <p className="text-gray-500 mt-5 text-sm leading-relaxed line-clamp-3">
                        {description}
                    </p>
                )}
                {metaLabel && (
                    <p className="text-primary/60 text-[10px] uppercase tracking-[0.35em] mt-6">
                        {metaLabel}
                    </p>
                )}
                <span className="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-primary group-hover:gap-4 transition-all duration-300">
                    {ctaLabel} <ArrowIcon className="w-4 h-4" />
                </span>
            </div>
        </Link>
    </AnimateInView>
)

export default FeaturedHero
