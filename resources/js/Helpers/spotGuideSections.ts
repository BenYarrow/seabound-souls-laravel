// Derives the spot-guide quick-nav's section list from the spotGuide prop:
// present-only, in the same order the sections render on the page. The free-form
// Content Builder blocks have no fixed name/anchor, so they are intentionally
// not represented here. Kept a pure function so it's unit-testable and the nav
// component stays declarative. The ids returned MUST match the anchor ids added
// to SpotGuide/Show.tsx.

/** One entry in the quick-nav. `id` is the target anchor; `label` is the chip text. */
export interface SpotGuideSection {
    id: string
    label: string
}

/** The subset of spot-guide fields the section guards read. */
interface SpotGuideLike {
    introduction_text: string | null
    gallery: unknown[]
    water_conditions: { content?: string } | null
    wind_conditions: { content?: string } | null
    when_to_go: string | null
    weather_records: Record<string, unknown>
    where_to_stay_intro: string | null
    stay_recommendations: unknown[]
    where_to_eat_intro: string | null
    eat_recommendations: unknown[]
    windsurfing_locations: unknown[]
    latitude: number | null
    longitude: number | null
    travelling_to: { content?: string } | null
    lessons_and_hire: { content?: string } | null
}

/**
 * Ordered section rules. Each mirrors the exact "has content" guard used in
 * SpotGuide/Show.tsx, in DOM order (Content Builder omitted).
 */
const SECTION_RULES: { id: string; label: string; present: (guide: SpotGuideLike) => boolean }[] = [
    { id: 'introduction', label: 'Introduction', present: (guide) => !!guide.introduction_text },
    { id: 'gallery', label: 'Gallery', present: (guide) => guide.gallery.length > 0 },
    { id: 'water-conditions', label: 'Water Conditions', present: (guide) => !!guide.water_conditions?.content },
    { id: 'wind-conditions', label: 'Wind Conditions', present: (guide) => !!guide.wind_conditions?.content },
    { id: 'when-to-go', label: 'When To Go', present: (guide) => !!guide.when_to_go },
    { id: 'weather', label: 'Weather', present: (guide) => Object.keys(guide.weather_records).length > 0 },
    { id: 'where-to-stay', label: 'Where To Stay', present: (guide) => !!guide.where_to_stay_intro || guide.stay_recommendations.length > 0 },
    { id: 'where-to-eat', label: 'Where To Eat', present: (guide) => !!guide.where_to_eat_intro || guide.eat_recommendations.length > 0 },
    { id: 'windsurfing-spots', label: 'Windsurfing Spots', present: (guide) => guide.windsurfing_locations.length > 0 },
    {
        id: 'explore-the-area',
        label: 'Explore The Area',
        // Mirrors Show.tsx: needs coords AND at least one mappable location.
        present: (guide) => !!(guide.latitude && guide.longitude)
            && (guide.stay_recommendations.length + guide.eat_recommendations.length + guide.windsurfing_locations.length) > 0,
    },
    { id: 'getting-there', label: 'Getting There', present: (guide) => !!guide.travelling_to?.content },
    { id: 'lessons-and-hire', label: 'Lessons & Hire', present: (guide) => !!guide.lessons_and_hire?.content },
]

/**
 * Build the present-only, DOM-ordered quick-nav section list for a spot guide.
 * @param spotGuide - The guide (only the guard fields are read).
 * @returns Sections that have content, each `{ id, label }`.
 */
export function buildSpotGuideSections(spotGuide: SpotGuideLike): SpotGuideSection[] {
    return SECTION_RULES
        .filter((rule) => rule.present(spotGuide))
        .map(({ id, label }) => ({ id, label }))
}
