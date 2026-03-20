import Layout from '@/Layouts/Layout'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import ContentBuilder from '@/Components/ContentBuilder'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import SpotOverview from '@/Components/Common/SpotOverview'
import ContentWithBackgroundImage from '@/Components/Content/ContentWithBackgroundImage'
import SpotGuideStatistics from '@/Components/SpotGuide/SpotGuideStatistics'

interface Props {
    spotGuide: {
        id: number
        title: string
        slug: string
        country: { name: string; slug: string; continent: string } | null
        timezone: string | null
        latitude: number | null
        longitude: number | null
        introduction_text: string | null
        spot_overview: Record<string, string> | null
        water_conditions: { content: string; text_right: boolean } | null
        wind_conditions: { content: string; text_right: boolean } | null
        when_to_go: string | null
        where_to_stay_intro: string | null
        where_to_eat_intro: string | null
        travelling_to: { content: string; text_right: boolean } | null
        lessons_and_hire: { content: string; text_right: boolean; latitude?: number; longitude?: number } | null
        content_blocks: any[] | null
        thumbnail: string
        static_masthead: string
        gallery: { url: string; alt: string }[]
        water_conditions_bg: string
        wind_conditions_bg: string
        travelling_to_bg: string
        lessons_and_hire_bg: string
        stay_recommendations: any[]
        eat_recommendations: any[]
        windsurfing_locations: any[]
        weather_records: Record<string, any[]>
    }
    meta: any
}

const Show = ({ spotGuide, meta }: Props) => {
    return (
        <Layout title={meta.title} description={meta.description} ogImage={meta.og_image}>
            <StaticMasthead
                imageUrl={spotGuide.static_masthead}
                title={spotGuide.title}
                subtitle={spotGuide.country?.name}
            >
                {spotGuide.spot_overview && (
                    <SpotOverview spotOverview={spotGuide.spot_overview} />
                )}
            </StaticMasthead>

            {/* Introduction */}
            {spotGuide.introduction_text && (
                <BlockWrapper>
                    <div
                        className="prose prose-lg max-w-none"
                        dangerouslySetInnerHTML={{ __html: spotGuide.introduction_text }}
                    />
                </BlockWrapper>
            )}

            {/* Gallery */}
            {spotGuide.gallery.length > 0 && (
                <div className="w-full py-8 bg-primary-lightest">
                    <div className="container mx-auto">
                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                            {spotGuide.gallery.map((img, i) => (
                                <img key={i} src={img.url} alt={img.alt} className="w-full aspect-square object-cover rounded-md" />
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {/* Water Conditions */}
            {spotGuide.water_conditions?.content && (
                <ContentWithBackgroundImage
                    backgroundImageUrl={spotGuide.water_conditions_bg}
                    content={spotGuide.water_conditions.content}
                    textRight={spotGuide.water_conditions.text_right}
                    title="Water Conditions"
                />
            )}

            {/* Wind Conditions */}
            {spotGuide.wind_conditions?.content && (
                <ContentWithBackgroundImage
                    backgroundImageUrl={spotGuide.wind_conditions_bg}
                    content={spotGuide.wind_conditions.content}
                    textRight={spotGuide.wind_conditions.text_right}
                    title="Wind Conditions"
                />
            )}

            {/* When To Go */}
            {spotGuide.when_to_go && (
                <BlockWrapper options={{ bgColourClass: 'bg-primary-lightest', fill: true }}>
                    <h2 className="text-3xl font-bold text-secondary mb-6">When To Go</h2>
                    <div
                        className="prose prose-lg max-w-none"
                        dangerouslySetInnerHTML={{ __html: spotGuide.when_to_go }}
                    />
                </BlockWrapper>
            )}

            {/* Weather Charts */}
            {Object.keys(spotGuide.weather_records).length > 0 && (
                <SpotGuideStatistics weatherRecords={spotGuide.weather_records} />
            )}

            {/* Where To Stay */}
            {(spotGuide.where_to_stay_intro || spotGuide.stay_recommendations.length > 0) && (
                <BlockWrapper options={{ bgColourClass: 'bg-white', fill: true }}>
                    <h2 className="text-3xl font-bold text-secondary mb-4">Where To Stay</h2>
                    {spotGuide.where_to_stay_intro && (
                        <div
                            className="prose prose-lg max-w-none mb-8"
                            dangerouslySetInnerHTML={{ __html: spotGuide.where_to_stay_intro }}
                        />
                    )}
                    {spotGuide.stay_recommendations.length > 0 && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {spotGuide.stay_recommendations.map((rec) => (
                                <div key={rec.id} className="rounded-lg overflow-hidden shadow-md bg-white">
                                    {rec.thumbnail && (
                                        <img src={rec.thumbnail} alt={rec.name} className="w-full aspect-[4/3] object-cover" />
                                    )}
                                    <div className="p-4">
                                        <h3 className="font-bold text-secondary text-lg">{rec.name}</h3>
                                        {rec.description && <p className="text-gray-600 text-sm mt-2">{rec.description}</p>}
                                        {rec.url && (
                                            <a href={rec.url} target="_blank" rel="noopener noreferrer" className="text-primary text-sm underline mt-2 inline-block">
                                                View on map
                                            </a>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </BlockWrapper>
            )}

            {/* Where To Eat */}
            {(spotGuide.where_to_eat_intro || spotGuide.eat_recommendations.length > 0) && (
                <BlockWrapper>
                    <h2 className="text-3xl font-bold text-secondary mb-4">Where To Eat</h2>
                    {spotGuide.where_to_eat_intro && (
                        <div
                            className="prose prose-lg max-w-none mb-8"
                            dangerouslySetInnerHTML={{ __html: spotGuide.where_to_eat_intro }}
                        />
                    )}
                    {spotGuide.eat_recommendations.length > 0 && (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {spotGuide.eat_recommendations.map((rec) => (
                                <div key={rec.id} className="rounded-lg overflow-hidden shadow-md bg-white">
                                    {rec.thumbnail && (
                                        <img src={rec.thumbnail} alt={rec.name} className="w-full aspect-[4/3] object-cover" />
                                    )}
                                    <div className="p-4">
                                        <h3 className="font-bold text-secondary text-lg">{rec.name}</h3>
                                        {rec.description && <p className="text-gray-600 text-sm mt-2">{rec.description}</p>}
                                        {rec.url && (
                                            <a href={rec.url} target="_blank" rel="noopener noreferrer" className="text-primary text-sm underline mt-2 inline-block">
                                                View location
                                            </a>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </BlockWrapper>
            )}

            {/* Windsurfing Locations */}
            {spotGuide.windsurfing_locations.length > 0 && (
                <BlockWrapper options={{ bgColourClass: 'bg-primary-lightest', fill: true }}>
                    <h2 className="text-3xl font-bold text-secondary mb-6">Windsurfing Spots</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {spotGuide.windsurfing_locations.map((loc) => (
                            <div key={loc.id} className="bg-white rounded-lg overflow-hidden shadow-md p-5">
                                {loc.thumbnail && (
                                    <img src={loc.thumbnail} alt={loc.name} className="w-full aspect-[4/3] object-cover rounded-md mb-3" />
                                )}
                                <h3 className="font-bold text-secondary text-lg">{loc.name}</h3>
                                {loc.description && <p className="text-gray-600 text-sm mt-2">{loc.description}</p>}
                            </div>
                        ))}
                    </div>
                </BlockWrapper>
            )}

            {/* Travelling To */}
            {spotGuide.travelling_to?.content && (
                <ContentWithBackgroundImage
                    backgroundImageUrl={spotGuide.travelling_to_bg}
                    content={spotGuide.travelling_to.content}
                    textRight={spotGuide.travelling_to.text_right}
                    title="Travelling To"
                />
            )}

            {/* Lessons & Hire */}
            {spotGuide.lessons_and_hire?.content && (
                <ContentWithBackgroundImage
                    backgroundImageUrl={spotGuide.lessons_and_hire_bg}
                    content={spotGuide.lessons_and_hire.content}
                    textRight={spotGuide.lessons_and_hire.text_right}
                    title="Lessons & Hire"
                />
            )}

            {/* Content Builder */}
            {spotGuide.content_blocks && <ContentBuilder blocks={spotGuide.content_blocks} />}
        </Layout>
    )
}

export default Show
