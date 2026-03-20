<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recommendation extends Model
{
    use SoftDeletes;

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

    public function scopeStay($query)
    {
        return $query->where('type', 'stay');
    }

    public function scopeEat($query)
    {
        return $query->where('type', 'eat');
    }
}
