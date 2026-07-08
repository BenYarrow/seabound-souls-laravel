/** An image plus its focal point (percentages, 0–100; 50/50 = centre). */
export interface FocalImage {
    url: string
    alt?: string
    focal_x?: number
    focal_y?: number
}
