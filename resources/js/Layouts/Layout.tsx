import { ReactNode } from 'react'
import { Head } from '@inertiajs/react'
import NavBar from '@/Components/Common/NavBar'
import Footer from '@/Components/Common/Footer'

interface LayoutProps {
    children: ReactNode
    title?: string
    description?: string
    ogImage?: string
}

const Layout = ({ children, title, description, ogImage }: LayoutProps) => {
    return (
        <>
            <Head>
                {title && <title>{title}</title>}
                {description && <meta name="description" content={description} />}
                {ogImage && <meta property="og:image" content={ogImage} />}
            </Head>
            <main className="w-full relative">
                <NavBar />
                {children}
                <Footer />
            </main>
        </>
    )
}

export default Layout
