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

    // Scroll-spy: the top-most intersecting section (just below the pinned bar)
    // is the active one. rootMargin trims the sticky-bar band off the top and
    // most of the lower viewport so the "current" section is the one at the top.
    useEffect(() => {
        if (sections.length < 2) return
        const observer = new IntersectionObserver(
            (entries) => {
                const topMost = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0]
                if (topMost) setActiveId(topMost.target.id)
            },
            { rootMargin: '-56px 0px -55% 0px', threshold: 0 }
        )
        sections.forEach((section) => {
            const el = document.getElementById(section.id)
            if (el) observer.observe(el)
        })
        return () => observer.disconnect()
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
