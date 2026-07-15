/**
 * TagMasthead — the hero at the top of blog tag surfaces (the /blog/tags hub and
 * each individual tag page).
 *
 * When the tag (or page) has a masthead image it delegates to the full-bleed
 * `StaticMasthead`. When there is no image it renders a designed, on-brand
 * fallback: a deep ocean-teal gradient with layered radial glows and a subtle
 * wave motif, so an image-less tag still feels intentional rather than a flat
 * colour bar. Pure CSS/SVG — no image request, so it stays fast.
 */
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import type { FocalImage } from '@/types/media'

interface TagMastheadProps {
    /** Focal-bearing masthead image, or null to render the gradient fallback. */
    image: FocalImage | null
    /** Main heading (the tag name, or "Topics" for the hub). */
    title: string
    /** Optional supporting line under the title. */
    subtitle?: string | null
    /** Small uppercase label above the title. */
    eyebrow?: string
}

/**
 * Render the tag hero — a real masthead image when present, otherwise the
 * designed gradient fallback.
 */
const TagMasthead = ({ image, title, subtitle, eyebrow }: TagMastheadProps) => {
    if (image) {
        return <StaticMasthead imageUrl={image} title={title} subtitle={subtitle ?? undefined} eyebrow={eyebrow} />
    }

    return (
        <div className="relative w-full h-[55vh] min-h-[420px] overflow-hidden bg-gradient-to-br from-primary-darker via-primary to-primary-darker">
            {/* Warm-teal glow, upper right — the primary source of "spark". */}
            <div className="absolute -top-1/4 -right-1/4 w-[70vw] h-[70vw] rounded-full bg-primary-lighter/25 blur-3xl pointer-events-none" />
            {/* Deeper secondary glow, lower left, for tonal depth. */}
            <div className="absolute -bottom-1/3 -left-1/4 w-[60vw] h-[60vw] rounded-full bg-primary/50 blur-3xl pointer-events-none" />

            {/* Layered wave motif along the bottom — evokes the ocean without an image. */}
            <svg
                className="absolute bottom-0 inset-x-0 w-full text-white/[0.07]"
                viewBox="0 0 1440 220"
                preserveAspectRatio="none"
                fill="currentColor"
                aria-hidden="true"
            >
                <path d="M0,140 C240,200 480,80 720,120 C960,160 1200,90 1440,130 L1440,220 L0,220 Z" />
            </svg>
            <svg
                className="absolute bottom-0 inset-x-0 w-full text-white/[0.05]"
                viewBox="0 0 1440 220"
                preserveAspectRatio="none"
                fill="currentColor"
                aria-hidden="true"
            >
                <path d="M0,170 C300,120 560,200 820,160 C1080,120 1260,190 1440,150 L1440,220 L0,220 Z" />
            </svg>

            {/* Top fade so the nav bar stays legible over the hero. */}
            <div className="absolute top-0 inset-x-0 h-40 bg-gradient-to-b from-black/30 to-transparent pointer-events-none" />

            {/* Content — editorial bottom-left, matching StaticMasthead's standard style. */}
            <div className="absolute inset-0 z-10 container mx-auto flex flex-col justify-end pointer-events-none">
                <div className="pb-14 md:pb-16">
                    {eyebrow && (
                        <p className="text-primary-lighter text-xs uppercase tracking-[0.4em] mb-3 font-light">
                            {eyebrow}
                        </p>
                    )}
                    <h1
                        className="font-title text-white uppercase leading-[0.9] drop-shadow-2xl"
                        style={{ fontSize: 'clamp(3rem, 10vw, 7rem)' }}
                    >
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="text-white/75 text-lg md:text-xl mt-4 max-w-xl">{subtitle}</p>
                    )}
                </div>
            </div>
        </div>
    )
}

export default TagMasthead
