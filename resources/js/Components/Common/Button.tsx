import { ReactNode } from 'react'
import { Link } from '@inertiajs/react'

type ButtonVariant = 'primary' | 'secondary' | 'outline' | 'ghost'

interface ButtonProps {
    children: ReactNode
    href?: string
    onClick?: () => void
    variant?: ButtonVariant
    className?: string
    type?: 'button' | 'submit' | 'reset'
    disabled?: boolean
    external?: boolean
}

const variantClasses: Record<ButtonVariant, string> = {
    primary: 'bg-primary text-white hover:bg-primary-darker border border-primary',
    secondary: 'bg-secondary text-white hover:opacity-80 border border-secondary',
    outline: 'bg-transparent text-primary border border-primary hover:bg-primary hover:text-white',
    ghost: 'bg-transparent text-primary hover:underline',
}

const Button = ({ children, href, onClick, variant = 'primary', className = '', type = 'button', disabled = false, external = false }: ButtonProps) => {
    const classes = `inline-flex items-center justify-center gap-x-2 px-6 py-3 font-semibold transition duration-200 text-sm uppercase ${variantClasses[variant]} ${disabled ? 'opacity-50 cursor-not-allowed' : ''} ${className}`

    if (href) {
        if (external) {
            return <a href={href} className={classes} target="_blank" rel="noopener noreferrer">{children}</a>
        }
        return <Link href={href} className={classes}>{children}</Link>
    }

    return (
        <button type={type} onClick={onClick} className={classes} disabled={disabled}>
            {children}
        </button>
    )
}

export default Button
