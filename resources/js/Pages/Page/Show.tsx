import Layout from '@/Layouts/Layout'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import MastheadSlider from '@/Components/Masthead/MastheadSlider'
import ContentBuilder from '@/Components/ContentBuilder'

interface Props {
    page: {
        id: number
        title: string
        slug: string
        template: string
        content_blocks: any[] | null
        static_masthead: string
        masthead_slider: string[]
    }
    meta: any
}

const Show = ({ page, meta }: Props) => {
    const hasSlider = page.masthead_slider?.length > 0

    return (
        <Layout title={meta.title} description={meta.description} ogImage={meta.og_image}>
            {hasSlider ? (
                <MastheadSlider slides={page.masthead_slider} title={page.title} />
            ) : page.static_masthead ? (
                <StaticMasthead imageUrl={page.static_masthead} title={page.title} />
            ) : (
                <div className="bg-primary py-16">
                    <div className="container mx-auto">
                        <h1 className="text-white text-4xl md:text-5xl font-bold">{page.title}</h1>
                    </div>
                </div>
            )}

            {page.content_blocks && <ContentBuilder blocks={page.content_blocks} />}
        </Layout>
    )
}

export default Show
