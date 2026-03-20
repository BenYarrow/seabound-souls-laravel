import Layout from '@/Layouts/Layout'
import Icon from '@/Components/Common/Icon'
import { useForm } from '@inertiajs/react'
import { FormEvent } from 'react'
import { faEnvelope, faCheck } from '@fortawesome/free-solid-svg-icons'
import { faInstagram, faYoutube } from '@fortawesome/free-brands-svg-icons'

interface Props {
    recaptchaSiteKey: string
    meta: { title: string; description: string }
}

const helpTopics = [
    'Destination recommendations',
    'Travel planning & trip advice',
    'Collaborations & partnerships',
    'General enquiries',
]

const socialMedia = [
    { icon: faYoutube,   href: 'https://www.youtube.com/@seabound_souls',      label: 'YouTube' },
    { icon: faInstagram, href: 'https://www.instagram.com/seaboundsouls/',      label: 'Instagram' },
]

const inputClass = [
    'w-full px-0 py-3 bg-transparent',
    'border-b border-secondary/20 focus:border-primary',
    'focus:outline-none text-secondary text-sm',
    'placeholder:text-secondary/25 transition-colors duration-200',
].join(' ')

const labelClass = 'block text-[10px] uppercase tracking-[0.25em] text-secondary/40 font-medium mb-2'

const Contact = ({ recaptchaSiteKey, meta }: Props) => {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        name: '',
        email: '',
        message: '',
        recaptcha_token: '',
    })

    const submit = (e: FormEvent) => {
        e.preventDefault()
        post('/contact', { onSuccess: () => reset() })
    }

    return (
        <Layout title={meta.title} description={meta.description}>
            <div className="grid grid-cols-1 lg:grid-cols-[5fr_7fr] min-h-[calc(100vh-5rem)]">

                {/* ─── Left: dark info panel ─── */}
                <div className="bg-secondary relative overflow-hidden flex flex-col justify-between p-10 lg:p-14">

                    {/* Ghost background text */}
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0 flex items-end justify-start overflow-hidden select-none"
                    >
                        <span
                            className="font-title text-white/[0.035] uppercase leading-none"
                            style={{ fontSize: 'clamp(5rem, 14vw, 14rem)', lineHeight: 0.85 }}
                        >
                            Seabound
                        </span>
                    </div>

                    {/* Top accent line */}
                    <div className="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-primary via-primary-lighter to-transparent" />

                    {/* Main content */}
                    <div className="relative z-10">
                        <span className="block w-8 h-0.5 bg-orange mb-6" />

                        <h1
                            className="font-display text-white leading-none tracking-wide"
                            style={{ fontSize: 'clamp(2.8rem, 4.5vw, 5rem)' }}
                        >
                            Get In Touch
                        </h1>

                        <p className="text-white/40 text-sm leading-relaxed mt-5 max-w-xs">
                            Planning your next windsurfing adventure? Need local knowledge
                            or want to work with us? We'd love to hear from you.
                        </p>

                        <ul className="mt-8 space-y-3">
                            {helpTopics.map((topic) => (
                                <li key={topic} className="flex items-center gap-3 text-white/45 text-sm">
                                    <span className="w-1 h-1 rounded-full bg-primary-lighter shrink-0" />
                                    {topic}
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Bottom: contact details */}
                    <div className="relative z-10 pt-12 space-y-5">
                        <div className="h-px bg-white/[0.07]" />

                        <a
                            href="mailto:seabound.souls@outlook.com"
                            className="flex items-center gap-3 text-white/45 hover:text-white text-sm transition-colors duration-200 group"
                        >
                            <Icon
                                icon={faEnvelope}
                                size="size-4"
                                customClasses="text-primary-lighter/60 group-hover:text-primary-lighter shrink-0 transition-colors duration-200"
                            />
                            seabound.souls@outlook.com
                        </a>

                        <div className="flex gap-3">
                            {socialMedia.map((social) => (
                                <a
                                    key={social.label}
                                    href={social.href}
                                    target="_blank"
                                    rel="nofollow external noopener noreferrer"
                                    aria-label={social.label}
                                    className="w-9 h-9 rounded-full border border-white/15 flex items-center justify-center text-white/45 hover:text-white hover:border-primary-lighter transition-all duration-300"
                                >
                                    <Icon icon={social.icon} size="size-4" />
                                </a>
                            ))}
                        </div>
                    </div>
                </div>

                {/* ─── Right: form panel ─── */}
                <div className="bg-cream flex items-center py-16 px-10 lg:px-16 xl:px-24">
                    <div className="w-full max-w-lg">

                        {wasSuccessful ? (
                            /* ── Success state ── */
                            <div className="text-center space-y-6 py-12">
                                <div className="w-14 h-14 border border-primary-lighter/50 rounded-full flex items-center justify-center mx-auto">
                                    <Icon icon={faCheck} size="size-6" customClasses="text-primary-lighter" />
                                </div>
                                <h2
                                    className="font-display text-secondary tracking-wide"
                                    style={{ fontSize: '2.5rem' }}
                                >
                                    Message Sent
                                </h2>
                                <p className="text-secondary/45 text-sm leading-relaxed max-w-xs mx-auto">
                                    Thanks for reaching out. We'll get back to you soon.
                                </p>
                            </div>

                        ) : (
                            /* ── Form ── */
                            <form onSubmit={submit} className="space-y-8">

                                <div>
                                    <span className="block w-6 h-0.5 bg-orange mb-5" />
                                    <p className="text-[10px] uppercase tracking-[0.3em] text-secondary/40">
                                        Send us a message
                                    </p>
                                </div>

                                {/* Name */}
                                <div>
                                    <label htmlFor="name" className={labelClass}>Name *</label>
                                    <input
                                        type="text"
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className={inputClass}
                                        placeholder="Your full name"
                                        required
                                    />
                                    {errors.name && (
                                        <p className="mt-1.5 text-[10px] text-red-500 uppercase tracking-wider">{errors.name}</p>
                                    )}
                                </div>

                                {/* Email */}
                                <div>
                                    <label htmlFor="email" className={labelClass}>Email *</label>
                                    <input
                                        type="email"
                                        id="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        className={inputClass}
                                        placeholder="your@email.com"
                                        required
                                    />
                                    {errors.email && (
                                        <p className="mt-1.5 text-[10px] text-red-500 uppercase tracking-wider">{errors.email}</p>
                                    )}
                                </div>

                                {/* Message */}
                                <div>
                                    <label htmlFor="message" className={labelClass}>Message *</label>
                                    <textarea
                                        id="message"
                                        rows={5}
                                        value={data.message}
                                        onChange={(e) => setData('message', e.target.value)}
                                        className={`${inputClass} resize-none`}
                                        placeholder="Tell us about your trip plans, question, or idea..."
                                        required
                                    />
                                    {errors.message && (
                                        <p className="mt-1.5 text-[10px] text-red-500 uppercase tracking-wider">{errors.message}</p>
                                    )}
                                </div>

                                {errors.recaptcha && (
                                    <p className="text-[10px] text-red-500 uppercase tracking-wider">{errors.recaptcha}</p>
                                )}

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full bg-primary text-white py-4 font-display tracking-widest hover:bg-primary-darker transition-colors duration-300 disabled:opacity-50"
                                    style={{ fontSize: '1.1rem' }}
                                >
                                    {processing ? 'Sending...' : 'Send Message'}
                                </button>
                            </form>
                        )}
                    </div>
                </div>

            </div>
        </Layout>
    )
}

export default Contact
