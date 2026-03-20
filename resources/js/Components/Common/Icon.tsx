import { FontAwesomeIcon } from '@fortawesome/react-fontawesome'
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core'

interface IconProps {
    icon: IconDefinition
    customClasses?: string
    size?: string
}

const Icon = ({ icon, customClasses = '', size = 'size-5' }: IconProps) => {
    return <FontAwesomeIcon icon={icon} className={`${size} ${customClasses}`} />
}

export default Icon
