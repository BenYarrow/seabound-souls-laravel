import { useRef } from 'react'
import { animate, useInView, useIsomorphicLayoutEffect } from 'framer-motion'

interface AnimatedCounterProps {
    from: number
    to: number
    className?: string
}

const AnimatedCounter = ({ from, to, className = '' }: AnimatedCounterProps) => {
    const ref = useRef<HTMLSpanElement>(null)
    const inView = useInView(ref, { once: true })

    useIsomorphicLayoutEffect(() => {
        const element = ref.current

        if (!element) return
        if (!inView) return

        if (window.matchMedia('(prefers-reduced-motion)').matches) {
            element.textContent = String(to)
            return
        }

        element.textContent = String(from)

        const controls = animate(from, to, {
            duration: 0.5,
            ease: 'easeOut',
            onUpdate(value) {
                element.textContent = value.toFixed(0)
            },
        })

        return () => {
            controls.stop()
        }
    }, [ref, from, to, inView])

    return <span ref={ref} className={className}></span>
}

export default AnimatedCounter
