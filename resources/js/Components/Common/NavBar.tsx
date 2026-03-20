import { useState, useEffect } from 'react'
import { Link, router } from '@inertiajs/react'
import { usePage } from '@inertiajs/react'
import { faBars, faSearch, faXmark } from '@fortawesome/free-solid-svg-icons'
import Icon from './Icon'

const navItems = [
    { title: 'Home', href: '/' },
    { title: 'About Us', href: '/about-us' },
    { title: 'Destinations', href: '/destinations' },
    { title: 'Blog', href: '/blog' },
    { title: 'Contact', href: '/contact' },
]

const NavBar = () => {
    const { url } = usePage()
    const [showMobileNav, setShowMobileNav] = useState(false)
    const [showSearch, setShowSearch] = useState(false)
    const [scrolled, setScrolled] = useState(false)

    const isHomepage = url === '/'

    useEffect(() => {
        setScrolled(false)
        setShowMobileNav(false)
        setShowSearch(false)

        if (!isHomepage) return

        const handleScroll = () => setScrolled(window.scrollY > 64)
        window.addEventListener('scroll', handleScroll, { passive: true })
        return () => window.removeEventListener('scroll', handleScroll)
    }, [url, isHomepage])

    const isTransparent = isHomepage && !scrolled && !showMobileNav

    const handleSearch = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        const form = e.currentTarget
        const input = form.elements.namedItem('q') as HTMLInputElement | null
        const value = input?.value.trim()
        if (!value) return
        router.get('/search', { q: value })
        setShowSearch(false)
    }

    const wrapperClasses = [
        'z-[1000] transition-all duration-500',
        isHomepage ? 'fixed top-0 left-0 right-0 w-full' : 'relative',
        isTransparent ? 'bg-transparent' : 'bg-primary shadow-md',
        !isTransparent ? 'border-b border-white/10' : '',
    ].filter(Boolean).join(' ')

    return (
        <div className={wrapperClasses}>
            {showSearch && (
                <div className={`w-full container mx-auto ${isHomepage ? 'bg-primary/95 backdrop-blur-sm' : ''}`}>
                    <form onSubmit={handleSearch} className="py-3">
                        <label htmlFor="search" className="sr-only">Search</label>
                        <input
                            type="text"
                            id="search"
                            name="q"
                            placeholder="Search the site..."
                            autoFocus
                            className="px-3 py-2 rounded-md w-full text-sm text-gray-900 placeholder:text-gray-600 focus:outline-none focus:ring-1 focus:ring-primary transition duration-200 ease-in-out shadow-sm"
                        />
                    </form>
                </div>
            )}

            <header className="h-[5rem] flex items-center">
                <div className="container mx-auto w-full flex items-center justify-between gap-x-6">
                    <div className="flex items-center gap-x-2">
                        <img src="/images/logo.png" alt="Seabound Souls" className="size-[50px] md:size-[60px]" loading="lazy" />
                        {url === '/' ? (
                            <h1 className="text-white text-2xl uppercase font-title whitespace-nowrap">Seabound Souls</h1>
                        ) : (
                            <Link href="/" className="text-white text-2xl uppercase font-title whitespace-nowrap">Seabound Souls</Link>
                        )}
                    </div>

                    <nav className={[
                        'max-lg:absolute max-lg:top-[5rem] max-lg:left-0 max-lg:w-full max-lg:bg-primary max-lg:container max-lg:mx-auto max-lg:z-10 max-lg:transition max-lg:duration-500',
                        showMobileNav ? '' : 'max-lg:translate-y-[calc(100vh+5rem)] max-lg:opacity-0 max-lg:pointer-events-none'
                    ].filter(Boolean).join(' ')}>
                        <ul className="max-lg:pt-6 max-lg:pb-8 flex flex-col lg:flex-row gap-y-3 lg:gap-x-6">
                            {navItems.map(({ href, title }) => (
                                <li key={title}>
                                    <Link
                                        href={href}
                                        className={[
                                            'text-base lg:text-sm uppercase tracking-wide font-medium whitespace-nowrap transition-opacity duration-200',
                                            url === href
                                                ? 'text-white opacity-100'
                                                : 'text-white/70 hover:text-white hover:opacity-100'
                                        ].join(' ')}
                                        onClick={() => setShowMobileNav(false)}
                                    >
                                        {title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <div className="flex items-center gap-x-5">
                        <button
                            onClick={() => setShowSearch(prev => !prev)}
                            aria-label="Search"
                            className={[
                                'text-white/70 hover:text-white transition-colors duration-200',
                                showMobileNav ? 'hidden' : 'block',
                            ].join(' ')}
                        >
                            <Icon icon={showSearch ? faXmark : faSearch} size="size-5" />
                        </button>

                        <button
                            onClick={() => { setShowMobileNav(!showMobileNav); setShowSearch(false) }}
                            className="lg:hidden text-white/70 hover:text-white transition-colors duration-200"
                            aria-label="Navigation menu"
                        >
                            <Icon icon={showMobileNav ? faXmark : faBars} size="size-5" />
                        </button>
                    </div>
                </div>
            </header>
        </div>
    )
}

export default NavBar
