<?php

// Curated blog tag. Owner-managed vocabulary assigned to blog posts and surfaced
// as crawlable /blog/tags/{slug} topic pages. The withPublishedPosts scope is the
// single gate every public surface (tag bar, chips, tag page, sitemap) uses so
// empty or draft-only tags never appear.

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'seo_title', 'seo_description', 'sort_order',
        'thumbnail_media_id', 'static_masthead_media_id',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Auto-fill the slug from the name when it is left blank, so the model is
     * usable without the admin form (tests, factories, seeders).
     */
    protected static function booted(): void
    {
        static::saving(function (Tag $tag) {
            if (blank($tag->slug) && filled($tag->name)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /** All blogs assigned this tag. */
    public function blogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class);
    }

    /** Card image shown for this tag on the /blog/tags hub (null → gradient fallback). */
    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'thumbnail_media_id');
    }

    /** Hero image at the top of the tag's own page (null → gradient fallback). */
    public function staticMastheadMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'static_masthead_media_id');
    }

    /** Only the published blogs assigned this tag (drafts excluded from public views). */
    public function publishedBlogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class)->where('is_published', true);
    }

    /**
     * Constrain to tags that have at least one published blog. Soft-deleted blogs
     * are excluded automatically by the Blog model's SoftDeletes global scope.
     * Used by every public surface so empty/draft-only tags never leak.
     */
    public function scopeWithPublishedPosts(Builder $query): Builder
    {
        return $query->whereHas('blogs', fn (Builder $blogQuery) => $blogQuery->where('is_published', true));
    }
}
