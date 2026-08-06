<?php

// A photographer credited for supplied imagery. Standalone by design — a credit
// is not an account (see the design spec). The record carries everything needed
// for a public profile page, but that page only goes live once profile_blocks
// has content: visibility is DERIVED, so no empty page can be published by
// accident and there is no manual flag to forget.

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Photographer extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Selectable targets for the image credit link, as key => admin label.
     *
     * The stored value is the KEY, never a copy of the URL — the URL is resolved
     * from `socials` at read time so changing a handle in one field updates every
     * credit on the site. 'profile' is only offered once the page is live.
     */
    public const CREDIT_LINK_OPTIONS = [
        'none' => 'No link',
        'profile' => 'Their page on this site',
        'website' => 'Personal website',
        'instagram' => 'Instagram',
        'youtube' => 'YouTube',
        'tiktok' => 'TikTok',
        'facebook' => 'Facebook',
        'x' => 'X (Twitter)',
    ];

    protected $fillable = [
        'name', 'slug', 'socials', 'credit_link', 'bio',
        'thumbnail_media_id', 'static_masthead_media_id',
        'profile_blocks', 'seo_title', 'seo_description', 'user_id',
    ];

    protected $casts = [
        'socials' => 'array',
        'profile_blocks' => 'array',
    ];

    /**
     * Auto-fill the slug from the name when left blank, so the model is usable
     * without the admin form (tests, factories, seeders).
     */
    protected static function booted(): void
    {
        static::saving(function (Photographer $photographer) {
            if (blank($photographer->slug) && filled($photographer->name)) {
                $photographer->slug = Str::slug($photographer->name);
            }

            // Filament's Builder writes [] when every block is removed. Normalising it
            // to null keeps the public-page gate a plain NOT NULL check: Postgres's
            // `json` type has no equality operator, so comparing against '[]' throws
            // in dev/production while passing silently on the SQLite test suite.
            if ($photographer->profile_blocks === []) {
                $photographer->profile_blocks = null;
            }
        });
    }

    /** Card image for the list_photographers roll-up block. */
    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'thumbnail_media_id');
    }

    /** Hero image at the top of their profile page. */
    public function staticMastheadMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'static_masthead_media_id');
    }

    /**
     * Every library image credited to this photographer.
     *
     * @return HasMany<MediaLibrary>
     */
    public function media(): HasMany
    {
        return $this->hasMany(MediaLibrary::class);
    }

    /**
     * Reserved for a future login. Nothing populates or reads this today; it
     * exists so granting an account later is a feature addition, not a migration.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this photographer has a live public page. Derived from content:
     * filling in the content builder IS the decision to publish, so there is no
     * separate switch and an untouched photographer never gets an empty page.
     */
    public function hasPublicPage(): bool
    {
        return filled($this->slug) && filled($this->profile_blocks);
    }

    /**
     * Constrain to photographers with a live page. Used by every public surface
     * (the roll-up block, the sitemap) so gated records never leak. A plain
     * NOT NULL check is sufficient because the `saving` hook above normalises
     * an empty [] content builder to null, so the empty case is never stored.
     */
    public function scopeWithPublicPage(Builder $query): Builder
    {
        return $query
            ->whereNotNull('slug')
            ->whereNotNull('profile_blocks');
    }

    /**
     * The credit shown against every image this photographer supplied.
     *
     * `url` is null whenever the target cannot be honoured — unset, 'none', an
     * unrecognised key, a socials entry with no value, or 'profile' while the
     * page is not live. Callers render a plain name in that case, so a dead link
     * is never produced.
     *
     * @return array{name: string, url: string|null}
     */
    public function creditPayload(): array
    {
        return ['name' => $this->name, 'url' => $this->resolveCreditUrl()];
    }

    /** Resolve the active credit target to a URL, or null if it cannot be honoured. */
    private function resolveCreditUrl(): ?string
    {
        $target = $this->credit_link;

        if (blank($target) || $target === 'none' || ! array_key_exists($target, self::CREDIT_LINK_OPTIONS)) {
            return null;
        }

        if ($target === 'profile') {
            return $this->hasPublicPage() ? "/photographers/{$this->slug}" : null;
        }

        $url = data_get($this->socials, $target);

        return filled($url) ? $url : null;
    }
}
