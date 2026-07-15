/**
 * StaticMasthead — the site-wide full-viewport page hero.
 *
 * When an `imageUrl` is supplied it renders that photo (focal-aware) with the
 * usual colour-grade + legibility fades. When no image is supplied it renders
 * the designed default: a deep ocean-teal gradient with layered radial glows and
 * a subtle wave motif (pure CSS/SVG, no image request) — so every image-less
 * page across the site gets an intentional hero rather than a flat colour bar.
 */
import { ReactNode } from 'react'
import CoverImage from '@/Components/Common/CoverImage'
import type { FocalImage } from '@/types/media'

interface StaticMastheadProps {
    /** Focal-bearing image object (or legacy string URL); null → gradient fallback. */
    imageUrl: FocalImage | string | null
    title: string
    subtitle?: string
    eyebrow?: string
    children?: ReactNode
}

const StaticMasthead = ({ imageUrl, title, subtitle, eyebrow, children }: StaticMastheadProps) => {
    return (
        <div className="relative w-full h-[calc(100vh-5rem)] overflow-visible bg-primary-darker">
            {imageUrl ? (
                <>
                    <CoverImage
                        image={imageUrl}
                        alt={title}
                        className="absolute inset-0 w-full h-full"
                    />
                    {/* Teal colour grade */}
                    <div className="absolute inset-0 bg-primary/20 mix-blend-multiply pointer-events-none" />
                    {/* Bottom fade */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent pointer-events-none" />
                    {/* Top fade (nav legibility) */}
                    <div className="absolute top-0 inset-x-0 h-40 bg-gradient-to-b from-black/40 to-transparent pointer-events-none" />
                </>
            ) : (
                /* Designed gradient fallback — the default masthead when no image
                   is supplied. Deep ocean-teal with glows + a wave motif. */
                <>
                    <div className="absolute inset-0 bg-gradient-to-br from-primary-darker via-primary to-primary-darker" />
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
                    {/* Light bottom scrim for text legibility over the gradient. */}
                    <div className="absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-transparent pointer-events-none" />
                    {/* Centred-title layouts (SpotGuide) sit mid-hero, where the bottom
                        scrim is transparent — a soft central vignette keeps the white
                        title legible there without dimming the corner glows/waves. */}
                    {children && (
                        <div
                            className="absolute inset-0 pointer-events-none"
                            style={{ background: 'radial-gradient(ellipse at center, rgba(0,0,0,0.35), transparent 65%)' }}
                        />
                    )}
                    {/* Top fade (nav legibility) */}
                    <div className="absolute top-0 inset-x-0 h-40 bg-gradient-to-b from-black/25 to-transparent pointer-events-none" />
                </>
            )}

            {children ? (
                /* SpotGuide layout — title centred, SpotOverview rendered as children */
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-10 text-center pointer-events-none">
                    <h1
                        className="font-title text-white uppercase leading-[0.9] drop-shadow-2xl"
                        style={{ fontSize: 'clamp(3rem, 9vw, 7rem)' }}
                    >
                        {title}
                    </h1>
                    {subtitle && <p className="text-white/80 text-xl mt-4">{subtitle}</p>}
                </div>
            ) : (
                /* Standard pages — editorial bottom-left style */
                <div className="absolute inset-0 z-10 container mx-auto flex flex-col justify-end pointer-events-none">
                    <div className="pb-16 md:pb-20">
                        {eyebrow && (
                            <p className="text-primary-lighter/90 text-xs uppercase tracking-[0.4em] mb-3 font-light">
                                {eyebrow}
                            </p>
                        )}
                        <h1
                            className="font-title text-white uppercase leading-[0.9] drop-shadow-2xl"
                            style={{ fontSize: 'clamp(3.5rem, 11vw, 9rem)' }}
                        >
                            {title}
                        </h1>
                        {subtitle && (
                            <p className="text-white/75 text-lg md:text-xl mt-4 max-w-lg">{subtitle}</p>
                        )}
                    </div>
                </div>
            )}

            {/* Scroll indicator — only without children */}
            {!children && (
                <div className="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex flex-col items-center gap-2 animate-scroll-nudge pointer-events-none">
                    <div className="w-px h-8 bg-white/40" />
                    <span className="text-white/40 text-[9px] uppercase tracking-[0.35em]">Scroll</span>
                </div>
            )}

            {children}
        </div>
    )
}

export default StaticMasthead
