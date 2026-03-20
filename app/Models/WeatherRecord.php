<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeatherRecord extends Model
{
    protected $fillable = [
        'spot_guide_id', 'year', 'month',
        'avg_temp', 'kts_wind', 'kts_gust',
        'mph_wind', 'mph_gust', 'kph_wind', 'kph_gust',
    ];

    protected $casts = [
        'avg_temp' => 'decimal:1',
        'kts_wind' => 'decimal:1',
        'kts_gust' => 'decimal:1',
    ];

    private const MONTH_NAMES = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
    ];

    public function getMonthNameAttribute(): string
    {
        return self::MONTH_NAMES[$this->month] ?? '';
    }

    public function spotGuide(): BelongsTo
    {
        return $this->belongsTo(SpotGuide::class);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    public function scopeForMonth($query, int $month)
    {
        return $query->where('month', $month);
    }
}
