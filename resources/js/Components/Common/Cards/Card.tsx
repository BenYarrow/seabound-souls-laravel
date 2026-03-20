import { Link } from '@inertiajs/react'

interface CardProps {
    title: string
    href: string
    imageUrl?: string
    subtitle?: string
    description?: string
}

const Card = ({ title, href, imageUrl, subtitle, description }: CardProps) => {
    return (
        <Link href={href} className="group block rounded-lg overflow-hidden shadow-md hover:shadow-xl transition-shadow duration-300 bg-white">
            {imageUrl && (
                <div className="aspect-[4/3] overflow-hidden">
                    <img
                        src={imageUrl}
                        alt={title}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />
                </div>
            )}
            <div className="p-4">
                {subtitle && <p className="text-sm text-primary font-semibold uppercase tracking-wide mb-1">{subtitle}</p>}
                <h3 className="text-lg font-bold text-secondary group-hover:text-primary transition-colors">{title}</h3>
                {description && <p className="text-sm text-gray-600 mt-2 line-clamp-2">{description}</p>}
            </div>
        </Link>
    )
}

export default Card
