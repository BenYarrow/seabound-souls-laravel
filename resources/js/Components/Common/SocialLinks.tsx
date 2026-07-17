/**
 * SocialLinks — a horizontal row of tappable brand icons for a contributor's
 * socials. Only platforms with a URL render; unknown keys are ignored.
 */
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import { faInstagram, faYoutube, faTiktok, faFacebook, faXTwitter } from '@fortawesome/free-brands-svg-icons'
import { faGlobe } from '@fortawesome/free-solid-svg-icons'
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core'

/** Map of platform key → its brand icon (website uses a globe). */
const ICONS: Record<string, IconDefinition> = {
    instagram: faInstagram,
    youtube: faYoutube,
    tiktok: faTiktok,
    facebook: faFacebook,
    x: faXTwitter,
    website: faGlobe,
}

interface Props {
    socials: Record<string, string>
    className?: string
}

/**
 * Render the filled socials as a row of teal icon buttons.
 *
 * @param socials platform→URL map (only filled entries are passed from the server)
 */
const SocialLinks = ({ socials, className = '' }: Props) => {
    const entries = Object.entries(socials).filter(([key, url]) => ICONS[key] && url)
    if (entries.length === 0) return null

    return (
        <div className={`flex items-center gap-3 ${className}`}>
            {entries.map(([key, url]) => (
                <a
                    key={key}
                    href={url}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={key}
                    className="w-10 h-10 flex items-center justify-center rounded-full bg-primary text-white hover:bg-primary-darker transition-colors duration-200"
                >
                    <FontAwesomeIcon icon={ICONS[key]} className="w-4 h-4" />
                </a>
            ))}
        </div>
    )
}

export default SocialLinks
