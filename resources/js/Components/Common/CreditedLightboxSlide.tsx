/**
 * CreditedLightboxSlide — the JSX "custom source" fed to fslightbox-react for
 * an image that carries a photographer credit. fslightbox-react@2.0.3 has no
 * caption support (verified against the installed package), so the only way
 * to show a credit inside the lightbox is to hand the library a JSX element
 * in place of its usual URL string; the library then renders that element as
 * the slide instead of managing the `<img>` itself. Used by both Gallery and
 * SingleImage, which otherwise duplicated this exact structure.
 */
import ImageCredit from './ImageCredit'
import type { ImageCreditData } from '@/Helpers/imageCredit'

interface CreditedLightboxSlideProps {
    url: string
    alt: string
    credit?: ImageCreditData | null
}

const CreditedLightboxSlide = ({ url, alt, credit }: CreditedLightboxSlideProps) => (
    <div className="relative flex items-center justify-center">
        <img
            src={url}
            alt={alt}
            /*
             * These bounds deliberately mirror fslightbox-react's OWN sizing
             * for its default (non-custom) slides, traced directly in the
             * installed node_modules/fslightbox-react/index.js (v2.0.3):
             *
             *   `sourceMargin` defaults to 0.05, so the library's internal
             *   height bound is (1 - 2*0.05) * innerHeight = 0.9 * innerHeight,
             *   applied UNCONDITIONALLY — hence `max-h-[90vh]` here, always.
             *
             *   The equivalent width bound (0.9 * innerWidth) is applied by
             *   the library ONLY when `innerWidth > 992` (a value hardcoded
             *   inside fslightbox, not a Tailwind breakpoint); at
             *   `innerWidth <= 992` it uses the full `innerWidth` with no
             *   horizontal margin at all. Hence the width cap here only
             *   kicks in above that point, via the arbitrary variant
             *   `min-[993px]:max-w-[90vw]` rather than Tailwind's default
             *   `lg` (1024px), which would reintroduce a mismatch between
             *   993px and 1024px.
             *
             * Without matching these, a credited (custom-source) slide
             * renders smaller than an uncredited (library-default) slide at
             * every viewport width — worst on mobile/tablet. Do NOT "tidy"
             * these into round numbers; they encode the library's internals.
             */
            className="max-h-[90vh] object-contain min-[993px]:max-w-[90vw]"
        />
        <ImageCredit credit={credit} />
    </div>
)

export default CreditedLightboxSlide
