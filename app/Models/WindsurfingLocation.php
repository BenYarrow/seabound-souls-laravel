<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WindsurfingLocation extends Model
{
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
