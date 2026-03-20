import RichText from './Content/RichText'
import ContentWithBackgroundImage from './Content/ContentWithBackgroundImage'
import AnimatedInfographic from './Content/AnimatedInfographic'
import SplitImageText from './Content/SplitImageText'
import SingleImage from './Content/SingleImage'
import ImagePair from './Content/ImagePair'
import Gallery from './Content/Gallery'

interface ContentBlock {
    type: string
    data: Record<string, any>
}

interface ContentBuilderProps {
    blocks: ContentBlock[]
}

const ContentBuilder = ({ blocks }: ContentBuilderProps) => {
    if (!blocks || blocks.length === 0) return null

    return (
        <>
            {blocks.map((block, index) => {
                switch (block.type) {
                    case 'rich_text':
                        return (
                            <RichText
                                key={index}
                                content={block.data.content}
                                bgColourClass={block.data.backgroundColour}
                                textAlign={block.data.textAlign}
                            />
                        )
                    case 'content_with_background_image':
                        return (
                            <ContentWithBackgroundImage
                                key={index}
                                backgroundImageUrl={block.data.backgroundImageMediaId_url}
                                content={block.data.content}
                                textRight={block.data.textRight}
                            />
                        )
                    case 'single_image':
                        return (
                            <SingleImage
                                key={index}
                                image={block.data.media_library_id_url}
                                backgroundColour={block.data.backgroundColour}
                            />
                        )
                    case 'image_pair':
                        return (
                            <ImagePair
                                key={index}
                                imageLeft={block.data.imageLeftMediaId_url}
                                imageRight={block.data.imageRightMediaId_url}
                                backgroundColour={block.data.backgroundColour}
                            />
                        )
                    case 'gallery':
                        return (
                            <Gallery
                                key={index}
                                images={block.data.mediaIds_urls ?? []}
                                thumbnailsOnly={block.data.thumbnailsOnly}
                            />
                        )
                    case 'split_image_text':
                        return (
                            <SplitImageText
                                key={index}
                                image={block.data.media_library_id_url}
                                text={block.data.text}
                                reverse={block.data.reverse ?? false}
                            />
                        )
                    case 'infographic':
                        return <AnimatedInfographic key={index} stats={block.data.stats} />
                    default:
                        return null
                }
            })}
        </>
    )
}

export default ContentBuilder
