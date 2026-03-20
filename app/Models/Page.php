<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Page extends Model
{
    use SoftDeletes, Searchable;

    protected $fillable = [
        'title', 'slug', 'template', 'content_blocks',
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

    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'thumbnail_media_id');
    }

    public function staticMastheadMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'static_masthead_media_id');
    }

    public function ogImageMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'og_image_media_id');
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'template' => $this->template,
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
