import { Link } from '@inertiajs/react'
import { faEnvelope } from '@fortawesome/free-solid-svg-icons'
import { faInstagram, faYoutube } from '@fortawesome/free-brands-svg-icons'
import Icon from './Icon'
import BlockWrapper from './BlockWrapper'

const socialMedia = [
    { icon: faYoutube, link: 'https://www.youtube.com/@seabound_souls', label: 'YouTube' },
    { icon: faInstagram, link: 'https://www.instagram.com/seaboundsouls/', label: 'Instagram' },
]

const Footer = () => {
    return (
        <footer>
            <BlockWrapper options={{ fill: true, bgColourClass: 'bg-primary', relative: true }}>
                <div className="max-lg:space-y-8 lg:flex lg:items-center lg:justify-start lg:gap-x-12">
                    <Link href="/" aria-label="Homepage">
                        <img src="/images/logo.png" alt="Seabound Souls" className="hidden sm:block w-[200px]" loading="lazy" />
                    </Link>

                    <div className="space-y-2 text-white">
                        <h3 className="text-white">
                            <Link href="/contact" className="text-xl md:text-2xl lg:text-2xl xl:text-3xl">Contact us</Link>
                        </h3>
                        <p className="max-w-sm md:text-lg">
                            Are you looking to book your next adventure and need some advice?{' '}
                            Or would you like to collaborate with us?{' '}
                            <Link href="/contact" className="underline">Get in touch</Link>.
                        </p>
                    </div>

                    <div className="space-y-4 lg:ml-12 xl:ml-20">
                        <div className="space-y-2 text-white">
                            <h3 className="text-white text-xl md:text-2xl">Email</h3>
                            <a href="mailto:seabound.souls@outlook.com" className="flex items-center gap-x-4 md:text-lg text-white hover:underline">
                                <Icon icon={faEnvelope} size="size-6" customClasses="text-white" />
                                seabound.souls@outlook.com
                            </a>
                        </div>

                        <div className="space-y-2 text-white">
                            <h3 className="text-white text-xl md:text-2xl">Get social</h3>
                            <div className="flex gap-x-4">
                                {socialMedia.map((social) => (
                                    <a key={social.label} href={social.link} target="_blank" rel="nofollow external noopener noreferrer" aria-label={social.label} className="text-white hover:opacity-80">
                                        <Icon icon={social.icon} size="size-6" customClasses="text-white" />
                                    </a>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </BlockWrapper>
        </footer>
    )
}

export default Footer
