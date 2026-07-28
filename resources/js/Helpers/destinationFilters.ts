// resources/js/Helpers/destinationFilters.ts
//
// Serialise/parse the destinations-page filter state to and from the URL query
// string, so a filtered view is shareable and bookmarkable. `min` is stored in
// the user's chosen unit; empty `spots` means "all destinations". **`spots` are
// serialised as slugs**, not titles (titles contain spaces/commas that would
// break the comma-join); the page resolves slugs → titles for ranking.

import { MIN_OPTIONS, type WindUnit } from '@/Helpers/sailableDays'

export type GroupBy = 'continent' | 'country' | 'global'

export interface DestinationFilters {
    month: number
    min: number
    unit: WindUnit
    group: GroupBy
    spots: string[]
}

const UNITS: WindUnit[] = ['kts', 'mph', 'kph']
const GROUPS: GroupBy[] = ['continent', 'country', 'global']
const DEFAULT_MIN = 20

/**
 * Build filter state from a URL query string, falling back to defaults for any
 * missing or invalid parameter. `defaults.month` is the current month (1-12),
 * supplied by the caller so this stays a pure function.
 */
export const parseFilters = (search: string, defaults: { month: number }): DestinationFilters => {
    const params = new URLSearchParams(search)

    const monthParam = Number(params.get('month'))
    const month = Number.isInteger(monthParam) && monthParam >= 1 && monthParam <= 12 ? monthParam : defaults.month

    const unitParam = params.get('unit') as WindUnit | null
    const unit = unitParam && UNITS.includes(unitParam) ? unitParam : 'kts'

    // why: the min <Select> only ever offers MIN_OPTIONS[unit] values, so a
    // hand-edited URL with an off-grid min (e.g. min=17 or min=999) must be
    // rejected — otherwise the dropdown and the applied ranking value fall out
    // of lockstep (dropdown shows the closest option, ranking uses the raw one).
    const minParam = Number(params.get('min'))
    const min = Number.isFinite(minParam) && MIN_OPTIONS[unit].includes(minParam) ? minParam : DEFAULT_MIN

    const groupParam = params.get('group') as GroupBy | null
    const group = groupParam && GROUPS.includes(groupParam) ? groupParam : 'continent'

    const spotsParam = params.get('spots')
    const spots = spotsParam ? spotsParam.split(',').filter(Boolean) : []

    return { month, min, unit, group, spots }
}

/** Serialise filter state to a flat query-param map (omitting empty spots). */
export const filtersToQuery = (filters: DestinationFilters): Record<string, string> => {
    const query: Record<string, string> = {
        month: String(filters.month),
        min: String(filters.min),
        unit: filters.unit,
        group: filters.group,
    }
    if (filters.spots.length > 0) {
        query.spots = filters.spots.join(',')
    }
    return query
}
