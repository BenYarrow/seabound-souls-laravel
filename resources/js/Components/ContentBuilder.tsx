import RichText from './Content/RichText'
import ContentWithBackgroundImage from './Content/ContentWithBackgroundImage'
import AnimatedInfographic from './Content/AnimatedInfographic'
import SplitImageText from './Content/SplitImageText'
import SingleImage from './Content/SingleImage'
import ImagePair from './Content/ImagePair'
import Gallery from './Content/Gallery'
import FeaturedGrid from './Common/FeaturedGrid'
import ContributorRollUp from '@/Components/Content/ContributorRollUp'
import PhotographerRollUp from '@/Components/Content/PhotographerRollUp'

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
                                // Trait now emits `_image` (focal-bearing object) instead of `_url`.
                                backgroundImageUrl={block.data.backgroundImageMediaId_image}
                                content={block.data.content}
                                textRight={block.data.textRight}
                            />
                        )
                    case 'single_image':
                        return (
                            <SingleImage
                                key={index}
                                image={block.data.media_library_id_image}
                                backgroundColour={block.data.backgroundColour}
                            />
                        )
                    case 'image_pair':
                        return (
                            <ImagePair
                                key={index}
                                imageLeft={block.data.imageLeftMediaId_image}
                                imageRight={block.data.imageRightMediaId_image}
                                backgroundColour={block.data.backgroundColour}
                            />
                        )
                    case 'gallery':
                        return (
                            <Gallery
                                key={index}
                                // Trait now emits `_images` (array of focal-bearing objects).
                                images={block.data.mediaIds_images ?? []}
                                thumbnailsOnly={block.data.thumbnailsOnly}
                            />
                        )
                    case 'split_image_text':
                        return (
                            <SplitImageText
                                key={index}
                                image={block.data.media_library_id_image}
                                text={block.data.text}
                                reverse={block.data.reverse ?? false}
                                backgroundColour={block.data.backgroundColour}
                            />
                        )
                    case 'infographic':
                        return <AnimatedInfographic key={index} stats={block.data.stats} />
                    case 'list_content_blogs':
                        return (
                            <FeaturedGrid
                                key={index}
                                title={block.data.blockTitle || 'From the blog'}
                                entries={block.data.customBlogEntries_resolved ?? []}
                                linkHref={block.data.viewAllUrl || '/blog'}
                                linkLabel={block.data.viewAllLabel || 'View all'}
                                backgroundColour={block.data.backgroundColour}
                                buildHref={(entry) => `/blog/${entry.slug}`}
                            />
                        )
                    case 'list_content_spot_guides':
                        return (
                            <FeaturedGrid
                                key={index}
                                title={block.data.blockTitle || 'Destinations'}
                                entries={block.data.customSpotGuideEntries_resolved ?? []}
                                linkHref={block.data.viewAllUrl || '/destinations'}
                                linkLabel={block.data.viewAllLabel || 'View all'}
                                backgroundColour={block.data.backgroundColour}
                                buildHref={(entry) => `/destinations/${entry.slug}`}
                            />
                        )
                    case 'contributor_roll_up':
                        return (
                            <ContributorRollUp
                                key={index}
                                heading={block.data.heading}
                                intro={block.data.intro}
                                contributors={block.data.contributors_resolved ?? []}
                            />
                        )
                    case 'list_photographers':
                        return (
                            <PhotographerRollUp
                                key={index}
                                heading={block.data.heading}
                                intro={block.data.intro}
                                photographers={block.data.photographers_resolved ?? []}
                            />
                        )
                    default:
                        return null
                }
            })}
        </>
    )
}

export default ContentBuilder
