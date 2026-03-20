interface RichTextProps {
    content: string
    bgColourClass?: string
    textAlign?: string
    className?: string
}

const DARK_BACKGROUNDS = ['bg-secondary', 'bg-primary', 'bg-primary-darker']

const RichText = ({ content, bgColourClass = '', textAlign = 'text-left', className = '' }: RichTextProps) => {
    if (!content) return null

    const invert = DARK_BACKGROUNDS.includes(bgColourClass)

    const proseClasses = [
        'prose prose-sm lg:prose-lg max-w-none',
        'prose-headings:uppercase',
        textAlign,
        `prose-headings:${textAlign}`,
        `prose-p:${textAlign}`,
        invert
            ? 'prose-headings:!text-primary-lighter prose-p:text-white prose-a:!text-primary-lighter marker:!text-white'
            : 'prose-headings:!text-primary prose-p:text-secondary prose-a:!text-primary marker:!text-primary',
    ].filter(Boolean).join(' ')

    return (
        <div className={`${bgColourClass} w-full`}>
            <div className={`container mx-auto py-10 md:py-14`}>
                <div
                    className={proseClasses}
                    dangerouslySetInnerHTML={{ __html: content }}
                />
            </div>
        </div>
    )
}

export default RichText
