<?php

// A place-to-stay or place-to-eat recommendation attached to a spot guide.
// The `type` enum ('stay'|'eat') drives the two lists on the destination page.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recommendation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'spot_guide_id', 'type', 'name', 'description', 'url',
        'latitude', 'longitude', 'sort_order',
        'thumbnail_media_id',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function spotGuide(): BelongsTo
    {
        return $this->belongsTo(SpotGuide::class);
    }

    public function thumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(MediaLibrary::class, 'thumbnail_media_id');
    }

    /** Accommodation recommendations. */
    public function scopeStay($query)
    {
        return $query->where('type', 'stay');
    }

    /** Food/drink recommendations. */
    public function scopeEat($query)
    {
        return $query->where('type', 'eat');
    }
}
