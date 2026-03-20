import { ReactNode } from 'react'

interface BlockWrapperOptions {
    bgColourClass?: string
    fill?: boolean
    relative?: boolean
    noPadding?: boolean
    noContainer?: boolean
}

interface BlockWrapperProps {
    children: ReactNode
    options?: BlockWrapperOptions
    className?: string
}

const BlockWrapper = ({ children, options = {}, className = '' }: BlockWrapperProps) => {
    const { bgColourClass = '', fill = false, relative = false, noPadding = false, noContainer = false } = options

    const wrapperClasses = [
        bgColourClass,
        fill ? 'w-full' : '',
        relative ? 'relative' : '',
    ].filter(Boolean).join(' ')

    const innerClasses = [
        'container mx-auto',
        noPadding ? '' : 'py-10 md:py-14 lg:py-16',
        className,
    ].filter(Boolean).join(' ')

    if (noContainer) {
        return (
            <section className={`${wrapperClasses} ${noPadding ? '' : 'py-10 md:py-14 lg:py-16'} ${className}`.trim()}>
                {children}
            </section>
        )
    }

    return (
        <section className={wrapperClasses}>
            <div className={innerClasses}>
                {children}
            </div>
        </section>
    )
}

export default BlockWrapper
