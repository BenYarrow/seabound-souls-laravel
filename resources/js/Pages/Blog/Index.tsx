import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import Card from '@/Components/Common/Cards/Card'
import { Link } from '@inertiajs/react'

interface Props {
    blogs: {
        data: any[]
        links: any
        meta: any
    }
    meta: { title: string; description: string }
}

const Index = ({ blogs, meta }: Props) => {
    return (
        <Layout title={meta.title} description={meta.description}>
            <div className="bg-primary py-16">
                <div className="container mx-auto">
                    <h1 className="text-white text-4xl md:text-5xl font-bold">Blog</h1>
                    <p className="text-white opacity-80 text-lg mt-3">Windsurfing tips and destination insights</p>
                </div>
            </div>

            <BlockWrapper>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    {blogs.data.map((blog) => (
                        <Card
                            key={blog.id}
                            title={blog.title}
                            href={`/blog/${blog.slug}`}
                            imageUrl={blog.thumbnail}
                            description={blog.seo_description}
                        />
                    ))}
                </div>

                {/* Pagination */}
                {blogs.meta?.last_page > 1 && (
                    <div className="mt-12 flex justify-center gap-x-2">
                        {blogs.links.map((link: any, i: number) => (
                            link.url ? (
                                <Link
                                    key={i}
                                    href={link.url}
                                    className={`px-4 py-2 rounded-md text-sm ${link.active ? 'bg-primary text-white' : 'border border-gray-300 hover:bg-gray-50'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span
                                    key={i}
                                    className="px-4 py-2 rounded-md text-sm text-gray-400 border border-gray-200"
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            )
                        ))}
                    </div>
                )}
            </BlockWrapper>
        </Layout>
    )
}

export default Index
