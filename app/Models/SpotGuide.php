<?php

// Spot guide (destination) model — the central content type. Soft-deletable and
// Scout-searchable. Most conditions/sections are JSON columns; images are FKs
// into the centralised media_library. Denormalises the country name onto the row
// so search can match on it without a join (see the saving hook below).

namespace App\Models;

use App\Jobs\FetchSpotWeatherJob;
use App\Models\Concerns\HasSingleFeatured;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Laravel\Scout\Searchable;

class SpotGuide extends Model
{
    use HasFactory, SoftDeletes, Searchable, HasSingleFeatured;

    /** Review lifecycle states. is_published is a separate, owner-only switch. */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED = 'approved';

    /**
     * In-memory default so a freshly-created (unsaved-refresh) instance reads as
     * draft immediately, mirroring the DB column default. review_status isn't
     * fillable, so this can't be spoofed via mass assignment.
     */
    protected $attributes = [
        'review_status' => self::STATUS_DRAFT,
    ];

    /**
     * Register model lifecycle hooks.
     *
     * saving — denormalises country_name onto the row whenever country_id
     *   changes, so Scout search can match on country without a JOIN.
     * created — dispatches FetchSpotWeatherJob for any new spot that already
     *   has coordinates, giving it weather data immediately rather than waiting
     *   for the next scheduled fetch-all run.
     */
    protected static function booted(): void
    {
        static::saving(function (SpotGuide $guide) {
            if ($guide->isDirty('country_id')) {
                $guide->country_name = Country::find($guide->country_id)?->name;
            }
        });

        // Featuring a guide is an owner-only editorial decision. The form field
        // and the inline list toggle are hidden from riders, but guard every
        // write path (incl. a crafted request) here: a non-owner can never change
        // is_featured — revert to the stored value on update, force false on create.
        static::saving(function (SpotGuide $guide) {
            if ($guide->isDirty('is_featured') && auth()->check() && ! auth()->user()->isOwner()) {
                // Cast guards against a not-yet-hydrated original (null) violating
                // the NOT NULL boolean column.
                $guide->is_featured = $guide->exists ? (bool) $guide->getOriginal('is_featured') : false;
            }
        });

        // Auto-fetch weather for a newly created spot as soon as it has
        // coordinates, so admins don't wait for the weekly command. Create-only
        // by design — editing coordinates later is handled by the dashboard
        // "Fetch all weather" button.
        static::created(function (SpotGuide $guide) {
            if ($guide->latitude !== null && $guide->longitude !== null) {
                FetchSpotWeatherJob::dispatch($guide->id);
            }
        });

        // Stamp the author on create so ownership/scoping and later attribution
        // work without every caller remembering to set it. An explicitly-provided
        // user_id (e.g. owner creating on someone's behalf) is respected.
        static::creating(function (SpotGuide $guide) {
            if ($guide->user_id === null && auth()->check()) {
                $guide->user_id = auth()->id();
            }
        });
    }

    protected $fillable = [
        'user_id', 'title', 'slug', 'country_id', 'country_name', 'latitude', 'longitude',
        'introduction_text', 'spot_overview', 'water_conditions', 'wind_conditions',
        'when_to_go', 'where_to_stay_intro', 'where_to_eat_intro',
        'travelling_to', 'lessons_and_hire', 'content_blocks',
        'seo_title', 'seo_description', 'seo_keywords',
        'is_published', 'is_featured', 'published_at',
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
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * The user who authored this guide. Nullable — a guide can outlive its
     * author (nullOnDelete).
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
     * Rider submits the guide for the owner's review. Editable-during-review by
     * design (no lock), so this only advances the status + stamps the time.
     */
    public function submitForReview(): void
    {
        $this->review_status = self::STATUS_IN_REVIEW;
        $this->submitted_at = now();
        $this->save();
    }

    /**
     * Owner approves and takes the guide live. published_at is set once (first
     * publish) and preserved on re-publish.
     */
    public function publish(): void
    {
        $this->is_published = true;
        $this->review_status = self::STATUS_APPROVED;
        $this->published_at ??= now();
        $this->reviewed_at = now();
        $this->save();
    }

    /**
     * Owner sends the guide back with feedback. Does NOT unpublish a live guide —
     * the owner unpublishes separately if that's wanted (house-owns-what's-live).
     */
    public function requestChanges(string $note): void
    {
        $this->review_status = self::STATUS_CHANGES_REQUESTED;
        $this->review_note = $note;
        $this->reviewed_at = now();
        $this->save();
    }

    /**
     * Sort a collection of spot guides "gustiest first" for the current month,
     * using this year's reading. Gusts are what light up a session, so they drive
     * the ranking. Guides with no reading for the current year+month sort last;
     * ties break alphabetically by title. Read per request (via now()) so the
     * order re-ranks as the month turns. Callers must eager-load weatherRecords.
     *
     * @param  Collection<int, SpotGuide>  $guides
     * @return Collection<int, SpotGuide>
     */
    public static function sortByGustiestThisMonth(Collection $guides): Collection
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $gustThisMonth = fn (SpotGuide $guide) => optional($guide->weatherRecords->first(
            fn ($record) => (int) $record->year === $currentYear && (int) $record->month === $currentMonth
        ))->kts_gust;

        return $guides->sort(function (SpotGuide $first, SpotGuide $second) use ($gustThisMonth) {
            $gustFirst = $gustThisMonth($first);
            $gustSecond = $gustThisMonth($second);

            if ($gustFirst === null && $gustSecond === null) {
                return strcmp($first->title, $second->title);
            }
            if ($gustFirst === null) {
                return 1; // no current-month data → sort last
            }
            if ($gustSecond === null) {
                return -1;
            }

            return ((float) $gustSecond <=> (float) $gustFirst) ?: strcmp($first->title, $second->title);
        })->values();
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
