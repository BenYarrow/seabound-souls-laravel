import { describe, it, expect } from 'vitest'
import { buildSpotGuideSections } from './spotGuideSections'

/** A guide with every section populated. */
const fullGuide = () => ({
    introduction_text: '<p>hi</p>',
    gallery: [{}],
    water_conditions: { content: '<p>w</p>' },
    wind_conditions: { content: '<p>w</p>' },
    when_to_go: '<p>go</p>',
    weather_records: { '2026': [{}] },
    where_to_stay_intro: '<p>stay</p>',
    stay_recommendations: [{}],
    where_to_eat_intro: '<p>eat</p>',
    eat_recommendations: [{}],
    windsurfing_locations: [{}],
    latitude: 1, longitude: 2,
    travelling_to: { content: '<p>t</p>' },
    lessons_and_hire: { content: '<p>l</p>' },
})

/** A guide with nothing populated. */
const emptyGuide = () => ({
    introduction_text: null, gallery: [], water_conditions: null, wind_conditions: null,
    when_to_go: null, weather_records: {}, where_to_stay_intro: null, stay_recommendations: [],
    where_to_eat_intro: null, eat_recommendations: [], windsurfing_locations: [],
    latitude: null, longitude: null, travelling_to: null, lessons_and_hire: null,
})

describe('buildSpotGuideSections', () => {
    it('returns all sections, in DOM order, for a fully-populated guide', () => {
        expect(buildSpotGuideSections(fullGuide()).map((s) => s.id)).toEqual([
            'introduction', 'gallery', 'water-conditions', 'wind-conditions', 'when-to-go',
            'weather', 'where-to-stay', 'where-to-eat', 'windsurfing-spots',
            'explore-the-area', 'getting-there', 'lessons-and-hire',
        ])
    })

    it('returns only present sections', () => {
        const g = { ...emptyGuide(), introduction_text: '<p>hi</p>', when_to_go: '<p>go</p>' }
        expect(buildSpotGuideSections(g).map((s) => s.id)).toEqual(['introduction', 'when-to-go'])
    })

    it('excludes content-builder blocks (nothing nameable → empty)', () => {
        // content_blocks is not a field the helper reads, so a guide with only
        // content blocks yields no nav sections.
        expect(buildSpotGuideSections(emptyGuide())).toEqual([])
    })

    it('shows explore-the-area only with coords AND at least one mappable location', () => {
        const withCoordsNoLocs = { ...emptyGuide(), latitude: 1, longitude: 2 }
        expect(buildSpotGuideSections(withCoordsNoLocs).map((s) => s.id)).not.toContain('explore-the-area')

        const withCoordsAndSpot = { ...emptyGuide(), latitude: 1, longitude: 2, windsurfing_locations: [{}] }
        expect(buildSpotGuideSections(withCoordsAndSpot).map((s) => s.id)).toContain('explore-the-area')

        const locsNoCoords = { ...emptyGuide(), stay_recommendations: [{}] }
        expect(buildSpotGuideSections(locsNoCoords).map((s) => s.id)).not.toContain('explore-the-area')
    })

    it('shows where-to-stay from either the intro OR a recommendation', () => {
        const introOnly = { ...emptyGuide(), where_to_stay_intro: '<p>x</p>' }
        expect(buildSpotGuideSections(introOnly).map((s) => s.id)).toContain('where-to-stay')

        const recOnly = { ...emptyGuide(), stay_recommendations: [{}] }
        expect(buildSpotGuideSections(recOnly).map((s) => s.id)).toContain('where-to-stay')
    })

    it('provides human labels', () => {
        const g = { ...emptyGuide(), travelling_to: { content: '<p>t</p>' } }
        expect(buildSpotGuideSections(g)).toEqual([{ id: 'getting-there', label: 'Getting There' }])
    })
})
