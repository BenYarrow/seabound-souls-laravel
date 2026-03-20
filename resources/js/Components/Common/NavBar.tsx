import { useState, useRef } from 'react'
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
    const searchRef = useRef<HTMLInputElement>(null)

    const handleSearch = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        const form = e.currentTarget
        const input = form.elements.namedItem('q') as HTMLInputElement | null
        const value = input?.value.trim()
        if (!value) return
        router.get('/search', { q: value })
        setShowSearch(false)
    }

    return (
        <div className="relative z-[1000] bg-primary" style={{ borderTop: '1px solid rgba(255,255,255,0.2)' }}>
            {showSearch && (
                <div className="w-full container mx-auto">
                    <form onSubmit={handleSearch} className="py-4">
                        <label htmlFor="search" className="sr-only">Search</label>
                        <input
                            type="text"
                            id="search"
                            name="q"
                            placeholder="Search the site..."
                            autoFocus
                            ref={searchRef}
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
                        'max-lg:absolute max-lg:top-[5rem] max-lg:left-0 max-lg:w-full max-lg:bg-white max-lg:container max-lg:mx-auto max-lg:z-10 max-lg:transition max-lg:duration-500',
                        showMobileNav ? '' : 'max-lg:translate-y-[calc(100vh+5rem)] max-lg:opacity-0 max-lg:pointer-events-none'
                    ].filter(Boolean).join(' ')}>
                        <ul className="max-lg:pt-6 flex flex-col lg:flex-row gap-y-2 lg:gap-x-6">
                            {navItems.map(({ href, title }) => (
                                <li key={title}>
                                    <Link
                                        href={href}
                                        className={[
                                            'text-lg whitespace-nowrap',
                                            url === href ? 'max-lg:underline text-primary lg:text-white font-semibold' : 'text-primary lg:text-white hover:underline'
                                        ].join(' ')}
                                        onClick={() => setShowMobileNav(false)}
                                    >
                                        {title}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <div className="flex items-center gap-x-4">
                        <button
                            onClick={() => {
                                setShowSearch(prev => !prev)
                                setTimeout(() => searchRef.current?.focus(), 100)
                            }}
                            aria-label="Search"
                            className={showMobileNav ? 'hidden' : 'block'}
                        >
                            <Icon icon={faSearch} customClasses="text-white" size="size-6" />
                        </button>

                        <button
                            onClick={() => { setShowMobileNav(!showMobileNav); setShowSearch(false) }}
                            className="lg:hidden"
                            aria-label="Navigation menu"
                        >
                            <Icon icon={showMobileNav ? faXmark : faBars} customClasses="text-white" size="size-6" />
                        </button>
                    </div>
                </div>
            </header>
        </div>
    )
}

export default NavBar
