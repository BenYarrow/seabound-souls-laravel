<?php

// One day of sailable-wind data for a spot guide: the 2nd-highest sustained-wind
// hour within the 9am-7pm sailing window, in knots. A day counts as "sailable"
// at a chosen minimum X when qualifying_wind_kts >= X (i.e. at least 2 hours blew
// at or above X). Feeds the client-side sailable-days ranking on /destinations.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SailableDay extends Model
{
    use HasFactory;

    protected $table = 'spot_sailable_days';

    protected $fillable = [
        'spot_guide_id', 'date', 'year', 'month', 'qualifying_wind_kts',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
        'qualifying_wind_kts' => 'decimal:1',
    ];

    public function spotGuide(): BelongsTo
    {
        return $this->belongsTo(SpotGuide::class);
    }
}
