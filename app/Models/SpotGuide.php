<?php

// Spot guide (destination) model — the central content type. Soft-deletable and
// Scout-searchable. Most conditions/sections are JSON columns; images are FKs
// into the centralised media_library. Denormalises the country name onto the row
// so search can match on it without a join (see the saving hook below).

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class SpotGuide extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    /**
     * Keep the denormalised country_name in step with the country_id FK. Only
     * re-resolves when country_id actually changed, so unrelated saves stay cheap.
     * country_name feeds the searchable array (toSearchableArray) so guides can be
     * found by country without joining the countries table at query time.
     */
    protected static function booted(): void
    {
        static::saving(function (SpotGuide $guide) {
            if ($guide->isDirty('country_id')) {
                $guide->country_name = Country::find($guide->country_id)?->name;
            }
        });
    }

    protected $fillable = [
        'title', 'slug', 'country_id', 'country_name', 'timezone', 'latitude', 'longitude',
        'introduction_text', 'spot_overview', 'water_conditions', 'wind_conditions',
        'when_to_go', 'where_to_stay_intro', 'where_to_eat_intro',
        'travelling_to', 'lessons_and_hire', 'content_blocks',
        'seo_title', 'seo_description', 'seo_keywords',
        'is_published', 'published_at',
        'thumbnail_media_id', 'static_masthead_media_id', 'og_image_media_id',
        'wind_conditions_bg_media_id', 'water_conditions_bg_media_id',
        'travelling_to_bg_media_id', 'lessons_and_hire_bg_media_id',
        'gallery_media_ids',
    ];

    protected $casts = [
        'spot_overview' => 'array',
        'water_conditions' => 'array',
        'wind_conditions' => 'array',
        'travelling_to' => 'array',
        'lessons_and_hire' => 'array',
        'content_blocks' => 'array',
        'seo_keywords' => 'array',
        'gallery_media_ids' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

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

    public function windConditionsBgMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'wind_conditions_bg_media_id');
    }

    public function waterConditionsBgMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'water_conditions_bg_media_id');
    }

    public function travellingToBgMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'travelling_to_bg_media_id');
    }

    public function lessonsAndHireBgMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'lessons_and_hire_bg_media_id');
    }

    /** Stay/eat recommendations, admin-ordered via sort_order. */
    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class)->orderBy('sort_order');
    }

    /** Launch spots within the guide, admin-ordered via sort_order. */
    public function windsurfingLocations(): HasMany
    {
        return $this->hasMany(WindsurfingLocation::class)->orderBy('sort_order');
    }

    /** Monthly climate averages; grouped/sorted for the charts in the controller. */
    public function weatherRecords(): HasMany
    {
        return $this->hasMany(WeatherRecord::class);
    }

    /**
     * Fields exposed to Laravel Scout. Includes the denormalised country_name and
     * tag-stripped intro/when-to-go text so free-text search matches on content.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'country_name' => $this->country_name,
            'introduction_text' => strip_tags((string) $this->introduction_text),
            'when_to_go' => strip_tags((string) $this->when_to_go),
        ];
    }

    /** Constrain a query to published guides only — keeps drafts off the site. */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
