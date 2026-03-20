import { cloneElement, createElement, useRef } from 'react'
import { useInView } from 'framer-motion'

interface AnimateInViewProps {
    children?: any
    tag?: string
    classes?: string
    inViewClasses?: string
    outViewClasses?: string
    delayClasses?: string
    durationClasses?: string
    once?: boolean
    animateChildren?: boolean
    delayIncrement?: number
}

const AnimateInView = ({
    children,
    tag = 'div',
    classes = '',
    inViewClasses = 'translate-y-0 opacity-100',
    outViewClasses = 'translate-y-10 opacity-0',
    delayClasses = 'delay-200',
    durationClasses = 'duration-500',
    once = true,
    animateChildren = false,
    delayIncrement = 200,
}: AnimateInViewProps) => {
    const ref = useRef(null)
    const isInView = useInView(ref, { once })

    const wrapperClasses = [classes, 'transition', delayClasses, durationClasses].join(' ')

    const childArray = Array.isArray(children) ? children : [children]

    return createElement(
        tag,
        {
            ref,
            className: `${wrapperClasses} ${!animateChildren ? (isInView ? inViewClasses : outViewClasses) : ''}`,
        },
        animateChildren
            ? childArray.map((child: any, index: number) =>
                  cloneElement(child, {
                      className: `${child.props?.className ?? ''} ${durationClasses} ${isInView ? inViewClasses : outViewClasses}`,
                      style: { transitionDelay: `${(index + 1) * delayIncrement}ms` },
                      key: index,
                  })
              )
            : children
    )
}

export default AnimateInView
