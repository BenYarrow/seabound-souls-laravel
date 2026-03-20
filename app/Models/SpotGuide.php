<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class SpotGuide extends Model
{
    use SoftDeletes, Searchable;

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

    public function recommendations(): HasMany
    {
        return $this->hasMany(Recommendation::class)->orderBy('sort_order');
    }

    public function windsurfingLocations(): HasMany
    {
        return $this->hasMany(WindsurfingLocation::class)->orderBy('sort_order');
    }

    public function weatherRecords(): HasMany
    {
        return $this->hasMany(WeatherRecord::class);
    }

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

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
