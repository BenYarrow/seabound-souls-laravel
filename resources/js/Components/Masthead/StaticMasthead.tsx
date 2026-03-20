import { ReactNode } from 'react'

interface StaticMastheadProps {
    imageUrl: string
    title: string
    subtitle?: string
    eyebrow?: string
    children?: ReactNode
}

const StaticMasthead = ({ imageUrl, title, subtitle, eyebrow, children }: StaticMastheadProps) => {
    return (
        <div className="relative w-full h-[calc(100vh-5rem)] overflow-visible bg-primary-darker">
            {imageUrl && (
                <img
                    src={imageUrl}
                    alt={title}
                    className="absolute inset-0 w-full h-full object-cover"
                />
            )}

            {/* Teal colour grade */}
            <div className="absolute inset-0 bg-primary/20 mix-blend-multiply pointer-events-none" />
            {/* Bottom fade */}
            <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent pointer-events-none" />
            {/* Top fade (nav legibility) */}
            <div className="absolute top-0 inset-x-0 h-40 bg-gradient-to-b from-black/40 to-transparent pointer-events-none" />

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
