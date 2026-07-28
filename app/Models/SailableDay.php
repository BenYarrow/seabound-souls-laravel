<?php

// One day of sailable-wind data for a spot guide: the 2nd-highest sustained-wind
// hour AND the 2nd-highest gust hour within the 9am-7pm sailing window, in knots.
// A day counts as "sailable" at a chosen minimum X when the ranking metric >= X
// (i.e. at least 2 hours blew/gusted at or above X). The sailable-days ranking on
// /destinations uses qualifying_gust_kts (sustained wind under-reads the felt
// wind at thermal/meltemi spots); qualifying_wind_kts is retained for a possible
// future sustained/gust toggle.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SailableDay extends Model
{
    use HasFactory;

    protected $table = 'spot_sailable_days';

    protected $fillable = [
        'spot_guide_id', 'date', 'year', 'month', 'qualifying_wind_kts', 'qualifying_gust_kts',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'qualifying_wind_kts' => 'decimal:1',
        'qualifying_gust_kts' => 'decimal:1',
    ];

    public function spotGuide(): BelongsTo
    {
        return $this->belongsTo(SpotGuide::class);
    }
}
