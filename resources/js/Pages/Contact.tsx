import Layout from '@/Layouts/Layout'
import BlockWrapper from '@/Components/Common/BlockWrapper'
import { useForm } from '@inertiajs/react'
import { FormEvent } from 'react'

interface Props {
    recaptchaSiteKey: string
    meta: { title: string; description: string }
    flash?: { success?: string }
}

const Contact = ({ recaptchaSiteKey, meta }: Props) => {
    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        name: '',
        email: '',
        message: '',
        recaptcha_token: '',
    })

    const submit = (e: FormEvent) => {
        e.preventDefault()
        // In a real implementation, you'd load reCAPTCHA and get the token first
        post('/contact', {
            onSuccess: () => reset(),
        })
    }

    return (
        <Layout title={meta.title} description={meta.description}>
            <div className="bg-primary py-16">
                <div className="container mx-auto">
                    <h1 className="text-white text-4xl md:text-5xl font-bold">Contact Us</h1>
                </div>
            </div>

            <BlockWrapper>
                <div className="max-w-2xl mx-auto">
                    {wasSuccessful && (
                        <div className="mb-6 p-4 bg-green-50 border border-green-200 rounded-md text-green-800">
                            Your message has been sent. We'll be in touch soon!
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-6">
                        <div>
                            <label htmlFor="name" className="block text-sm font-medium text-secondary mb-2">Name *</label>
                            <input
                                type="text"
                                id="name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                className="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary"
                                required
                            />
                            {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
                        </div>

                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-secondary mb-2">Email *</label>
                            <input
                                type="email"
                                id="email"
                                value={data.email}
                                onChange={e => setData('email', e.target.value)}
                                className="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary"
                                required
                            />
                            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
                        </div>

                        <div>
                            <label htmlFor="message" className="block text-sm font-medium text-secondary mb-2">Message *</label>
                            <textarea
                                id="message"
                                rows={6}
                                value={data.message}
                                onChange={e => setData('message', e.target.value)}
                                className="w-full px-4 py-3 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-primary"
                                required
                            />
                            {errors.message && <p className="mt-1 text-sm text-red-600">{errors.message}</p>}
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full bg-primary text-white py-3 px-6 rounded-md font-semibold hover:bg-primary-darker transition-colors disabled:opacity-50"
                        >
                            {processing ? 'Sending...' : 'Send Message'}
                        </button>
                    </form>
                </div>
            </BlockWrapper>
        </Layout>
    )
}

export default Contact
