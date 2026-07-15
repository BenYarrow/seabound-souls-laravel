import Layout from '@/Layouts/Layout'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import MastheadSlider from '@/Components/Masthead/MastheadSlider'
import ContentBuilder from '@/Components/ContentBuilder'
import type { FocalImage } from '@/types/media'

interface Props {
    page: {
        id: number
        title: string
        slug: string
        template: string
        content_blocks: any[] | null
        /** Focal-bearing image object (or null when no masthead). */
        static_masthead: FocalImage | null
        masthead_slider: FocalImage[]
    }
    meta: any
}

const Show = ({ page, meta }: Props) => {
    const hasSlider = page.masthead_slider?.length > 0

    return (
        <Layout title={meta.title} description={meta.description} keywords={meta.keywords} ogImage={meta.og_image}>
            {hasSlider ? (
                <MastheadSlider slides={page.masthead_slider} title={page.title} />
            ) : (
                <StaticMasthead imageUrl={page.static_masthead} title={page.title} />
            )}

            {page.content_blocks && <ContentBuilder blocks={page.content_blocks} />}
        </Layout>
    )
}

export default Show
