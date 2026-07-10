/**
 * Chart colour helpers for the destinations weather visualisations.
 *
 * Single-spot charts use the fixed `chartColors` trio. Multi-destination
 * comparison charts use `getSpotGuideColours`, which GENERATES a colour per
 * destination via the golden angle — so the palette never runs out as new
 * spot guides are added, and every line stays visually distinct.
 */

/** Fixed colours for single-spot charts (wind / gust / temp). Out of scope
 *  for the destinations light-theme work — left unchanged. */
export const chartColors = {
    wind: '#8884d8',
    gust: '#82ca9d',
    temp: '#ffc658',
}

/**
 * Muted saturation / lightness, tuned so generated lines read as one calm
 * family on a light (cream) chart card — vivid enough to tell apart, soft
 * enough not to vibrate. Shared by every generated colour.
 */
const CHART_SATURATION = 48 // %
const CHART_LIGHTNESS = 48 // %

/**
 * The golden angle, in degrees. Spacing hues by it maximises separation
 * between consecutive indices, so each destination line is distinct for any
 * number of guides.
 */
const GOLDEN_ANGLE = 137.508

/**
 * Deterministic, distinct chart colour for the Nth destination. No fixed
 * palette to exhaust — index N always yields the same hue, independent of how
 * many destinations there are in total.
 * @param index - Zero-based position of the destination in the list.
 * @returns An `hsl(...)` colour string at the fixed muted saturation/lightness.
 */
const colourForIndex = (index: number): string => {
    const hue = (index * GOLDEN_ANGLE) % 360
    return `hsl(${Number(hue.toFixed(1))}, ${CHART_SATURATION}%, ${CHART_LIGHTNESS}%)`
}

/**
 * Map each destination title to a generated muted chart colour, assigned by
 * its position in the given (already display-ordered) list.
 * @param titles - Destination titles, in display order.
 * @returns A title→colour lookup.
 */
export const getSpotGuideColours = (titles: string[]): Record<string, string> => {
    const colours: Record<string, string> = {}
    titles.forEach((title, index) => {
        colours[title] = colourForIndex(index)
    })
    return colours
}
