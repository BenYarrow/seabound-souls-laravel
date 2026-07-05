<?php

// A specific launch spot / sailing location within a spot guide, shown as a
// pin on the map and a card on the destination page.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WindsurfingLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'spot_guide_id', 'name', 'description', 'latitude', 'longitude', 'sort_order',
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
}
