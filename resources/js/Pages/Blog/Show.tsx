import Layout from '@/Layouts/Layout'
import StaticMasthead from '@/Components/Masthead/StaticMasthead'
import MastheadSlider from '@/Components/Masthead/MastheadSlider'
import ContentBuilder from '@/Components/ContentBuilder'
import { formatDate } from '@/Helpers/helpers'

interface Props {
    blog: {
        id: number
        title: string
        slug: string
        content_blocks: any[] | null
        published_at: string | null
        thumbnail: string
        static_masthead: string
        masthead_slider: string[]
    }
    meta: any
}

const Show = ({ blog, meta }: Props) => {
    const hasSlider = blog.masthead_slider?.length > 0

    return (
        <Layout title={meta.title} description={meta.description} ogImage={meta.og_image}>
            {hasSlider ? (
                <MastheadSlider slides={blog.masthead_slider} title={blog.title} />
            ) : (
                <StaticMasthead
                    imageUrl={blog.static_masthead}
                    title={blog.title}
                    subtitle={blog.published_at ? formatDate(blog.published_at) : undefined}
                />
            )}

            {blog.content_blocks && <ContentBuilder blocks={blog.content_blocks} />}
        </Layout>
    )
}

export default Show
