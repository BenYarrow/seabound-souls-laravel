<?php

// Blog post model. Soft-deletable and Scout-searchable. Images are not stored
// on the row — they're referenced by FK into the centralised media_library
// (thumbnail, static masthead, OG image, plus a JSON list of slider image ids).

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Blog extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    protected $fillable = [
        'title', 'slug', 'content_blocks',
        'seo_title', 'seo_description', 'seo_keywords',
        'is_published', 'published_at',
        'thumbnail_media_id', 'static_masthead_media_id', 'og_image_media_id',
        'masthead_slider_media_ids',
    ];

    protected $casts = [
        'content_blocks' => 'array',
        'seo_keywords' => 'array',
        'masthead_slider_media_ids' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    /** Listing/card thumbnail image. */
    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'thumbnail_media_id');
    }

    /** Full-width masthead image shown at the top of the post. */
    public function staticMastheadMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'static_masthead_media_id');
    }

    /** Open Graph / social-share image; falls back to the thumbnail when unset. */
    public function ogImageMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'og_image_media_id');
    }

    /**
     * Fields exposed to Laravel Scout. Deliberately minimal — only what the
     * search results list needs to render and link a hit.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
        ];
    }

    /**
     * Constrain a query to published posts only. Used by the public controllers
     * so drafts never leak onto the live site.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
