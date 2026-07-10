// Sticky, content-aware quick-nav for the spot-guide page. Renders a horizontal
// bar of section links that pins to the top as you scroll the guide; the active
// section (via IntersectionObserver) is highlighted, clicking smooth-scrolls to
// it, and on mobile the row scrolls horizontally with the active chip auto-
// centred. Renders nothing for fewer than 2 sections. Frontend-only; targets are
// the #id anchors on the sections in SpotGuide/Show.tsx.

import { useEffect, useRef, useState } from 'react'
import type { SpotGuideSection } from '@/Helpers/spotGuideSections'

interface Props {
    /** Present sections in display order (from buildSpotGuideSections). */
    sections: SpotGuideSection[]
}

/**
 * Render the sticky spot-guide quick-nav.
 * @param props - See {@link Props}.
 */
const SpotGuideNav = ({ sections }: Props) => {
    const [activeId, setActiveId] = useState<string | null>(sections[0]?.id ?? null)
    const barRef = useRef<HTMLDivElement | null>(null)

    // Scroll-spy: the active section is the LAST one whose top has scrolled up
    // under the pinned bar (i.e. the section currently occupying the top of the
    // viewport). A passive scroll/resize listener recomputes from the live rects —
    // cheap for a dozen sections, and reliable everywhere (an IntersectionObserver
    // here would be more elegant but doesn't fire in non-painting/headless
    // contexts, which would silently break the highlight).
    useEffect(() => {
        if (sections.length < 2) return
        const BAR_OFFSET = 72 // px below the viewport top — just under the sticky bar
        const recompute = () => {
            let current = sections[0].id
            for (const section of sections) {
                const el = document.getElementById(section.id)
                if (el && el.getBoundingClientRect().top <= BAR_OFFSET) current = section.id
            }
            setActiveId(current)
        }
        // rAF-throttle so we do the rect reads at most once per frame, not per
        // scroll event (avoids forced-layout jank on a long guide).
        let ticking = false
        const onScroll = () => {
            if (ticking) return
            ticking = true
            requestAnimationFrame(() => {
                recompute()
                ticking = false
            })
        }
        recompute()
        window.addEventListener('scroll', onScroll, { passive: true })
        window.addEventListener('resize', onScroll, { passive: true })
        return () => {
            window.removeEventListener('scroll', onScroll)
            window.removeEventListener('resize', onScroll)
        }
    }, [sections])

    // Keep the active chip visible in the horizontally-scrolling strip (mobile).
    useEffect(() => {
        if (!activeId || !barRef.current) return
        const chip = barRef.current.querySelector<HTMLElement>(`[data-section="${activeId}"]`)
        chip?.scrollIntoView({ inline: 'center', block: 'nearest', behavior: 'smooth' })
    }, [activeId])

    if (sections.length < 2) return null

    /** Smooth-scroll to a section; scroll-mt on the target clears the pinned bar. */
    const goTo = (id: string) => {
        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }

    return (
        <nav aria-label="Spot guide sections" className="sticky top-0 z-40 bg-white border-b border-secondary/10">
            <div className="relative container mx-auto">
                <div className="flex items-stretch">
                    <div className="w-1 bg-orange shrink-0 my-2 rounded-full" />
                    <div
                        ref={barRef}
                        className="flex gap-1 items-center overflow-x-auto py-2 pl-3 [&::-webkit-scrollbar]:hidden"
                        style={{ scrollbarWidth: 'none' }}
                    >
                        {sections.map((section) => {
                            const active = section.id === activeId
                            return (
                                <button
                                    key={section.id}
                                    data-section={section.id}
                                    aria-current={active ? 'true' : undefined}
                                    onClick={() => goTo(section.id)}
                                    className={`shrink-0 text-[11px] uppercase tracking-[0.15em] px-3 py-1.5 rounded-sm transition-colors duration-200 ${
                                        active ? 'bg-primary text-white' : 'text-secondary/70 hover:text-secondary'
                                    }`}
                                >
                                    {section.label}
                                </button>
                            )
                        })}
                    </div>
                </div>
                {/* Right-edge fade hints there are more chips to swipe (mobile). */}
                <div className="pointer-events-none absolute right-0 top-0 bottom-0 w-10 bg-gradient-to-r from-transparent to-white md:hidden" />
            </div>
        </nav>
    )
}

export default SpotGuideNav
