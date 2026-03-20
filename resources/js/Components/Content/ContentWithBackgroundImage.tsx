interface ContentWithBackgroundImageProps {
    backgroundImageUrl: string
    content: string
    textRight?: boolean
    title?: string
}

const ContentWithBackgroundImage = ({ backgroundImageUrl, content, textRight = false, title }: ContentWithBackgroundImageProps) => {
    const textWrapperClasses = [
        'max-lg:container max-lg:mx-auto flex items-center w-full h-full',
        'lg:w-1/2',
        textRight ? 'lg:ml-auto lg:justify-end' : 'lg:mr-auto lg:justify-start'
    ].join(' ')

    return (
        <div className="relative h-[calc(100vh-5rem)] w-full flex overflow-hidden">
            {backgroundImageUrl && (
                <img src={backgroundImageUrl} alt="" className="absolute inset-0 w-full h-full object-cover" />
            )}
            <div className={textWrapperClasses}>
                <div className="relative bg-secondary/90 text-white max-lg:h-[80vh] lg:h-full flex items-center">
                    <div className="w-full overflow-y-auto max-h-full p-8 lg:p-12">
                        {title && (
                            <h2 className="text-center font-bold tracking-wider text-white mb-6">
                                {title}
                            </h2>
                        )}
                        <div
                            className="prose prose-invert max-w-none text-center lg:text-left"
                            dangerouslySetInnerHTML={{ __html: content }}
                        />
                    </div>
                </div>
            </div>
        </div>
    )
}

export default ContentWithBackgroundImage
