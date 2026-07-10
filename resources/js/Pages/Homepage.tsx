import Layout from '@/Layouts/Layout'
import ContentBuilder from '@/Components/ContentBuilder'
import MastheadSlider from '@/Components/Masthead/MastheadSlider'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'

interface Props {
    page: any
    meta: { title: string; description: string; keywords?: string[]; og_image?: string }
}

const Homepage = ({ page, meta }: Props) => {
    const hasSlider = page?.masthead_slider?.length > 0
    const hasStaticMasthead = !hasSlider && page?.static_masthead

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
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
        </Layout>
    )
}

export default Homepage
