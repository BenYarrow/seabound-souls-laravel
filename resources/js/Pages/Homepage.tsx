import Layout from '@/Layouts/Layout'
import ContentBuilder from '@/Components/ContentBuilder'
import FeaturedGrid from '@/Components/Common/FeaturedGrid'
import MastheadSlider from '@/Components/Masthead/MastheadSlider'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'

interface Props {
    page: any
    featuredSpotGuides: any[]
    recentBlogs: any[]
    meta: { title: string; description: string }
}

const Homepage = ({ page, featuredSpotGuides, recentBlogs, meta }: Props) => {
    const hasSlider = page?.masthead_slider?.length > 0
    const hasStaticMasthead = !hasSlider && page?.static_masthead

    return (
        <Layout title={meta.title} description={meta.description}>
            {hasSlider ? (
                <MastheadSlider slides={page.masthead_slider} title={page?.title || 'Seabound Souls'} />
            ) : hasStaticMasthead ? (
                <StaticMasthead imageUrl={page.static_masthead} title={page?.title || 'Seabound Souls'} />
            ) : (
                <div className="bg-primary py-24">
                    <div className="container mx-auto text-center">
                        <h1 className="text-white text-5xl md:text-6xl font-title uppercase">Seabound Souls</h1>
                        <p className="text-white opacity-80 text-xl mt-4">Your ultimate windsurfing destination guide</p>
                    </div>
                </div>
            )}

            {page?.content_blocks && <ContentBuilder blocks={page.content_blocks} />}

            <FeaturedGrid
                title="Featured Spot Guides"
                entries={featuredSpotGuides.map((guide) => ({
                    ...guide,
                    subtitle: guide.country,
                }))}
                linkHref="/destinations"
                linkLabel="View all"
                linkScreenReaderLabel="spot guides"
                backgroundColour="bg-secondary"
                buildHref={(entry) => `/destinations/${entry.slug}`}
            />

            <FeaturedGrid
                title="Latest From The Blog"
                entries={recentBlogs}
                linkHref="/blog"
                linkLabel="Read More"
                backgroundColour="bg-white"
                buildHref={(entry) => `/blog/${entry.slug}`}
            />
        </Layout>
    )
}

export default Homepage
