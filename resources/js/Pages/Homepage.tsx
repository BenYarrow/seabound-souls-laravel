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

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            {hasSlider ? (
                <MastheadSlider slides={page.masthead_slider} title={page?.title || 'Seabound Sessions'} />
            ) : (
                <StaticMasthead
                    imageUrl={page?.static_masthead ?? null}
                    title={page?.title || 'Seabound Sessions'}
                    subtitle="Your ultimate windsurfing destination guide"
                />
            )}

            {page?.content_blocks && <ContentBuilder blocks={page.content_blocks} />}
        </Layout>
    )
}

export default Homepage
