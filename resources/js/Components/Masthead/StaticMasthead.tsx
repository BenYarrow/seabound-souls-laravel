import { ReactNode } from 'react'

interface StaticMastheadProps {
    imageUrl: string
    title: string
    subtitle?: string
    children?: ReactNode
}

const StaticMasthead = ({ imageUrl, title, subtitle, children }: StaticMastheadProps) => {
    return (
        <div className="relative w-full h-[calc(100vh-5rem)] overflow-hidden bg-primary-lighter">
            {imageUrl && (
                <img
                    src={imageUrl}
                    alt={title}
                    className="absolute inset-0 w-full h-full object-cover"
                />
            )}
            {children ? (
                <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 z-10 text-center">
                    <h1 className="text-white text-4xl md:text-5xl lg:text-6xl font-title uppercase drop-shadow-lg">
                        {title}
                    </h1>
                </div>
            ) : (
                <div className="absolute bottom-0 left-0 right-0 z-10 bg-secondary/90 py-4 flex justify-center">
                    <h1 className="text-white text-4xl md:text-5xl lg:text-6xl font-title uppercase drop-shadow-lg">
                        {title}
                    </h1>
                    {subtitle && (
                        <p className="text-white text-xl md:text-2xl mt-2 drop-shadow-md">{subtitle}</p>
                    )}
                </div>
            )}
            {children}
        </div>
    )
}

export default StaticMasthead
