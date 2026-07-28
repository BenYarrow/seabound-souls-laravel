<?php

// Adds `qualifying_gust_kts` to `spot_sailable_days`: the day's 2nd-highest
// sailing-window (9am-7pm) GUST hour, in kts, alongside the existing sustained
// -wind column. Real-world validation (Karpathos, meltemi/thermal spots) showed
// Open-Meteo's sustained 10m wind under-reads the felt wind at these locations,
// while gusts track what sailors actually ride — so the sailable-day RANKING
// switches to gusts (see WeatherFetcher::fetchForSpot and DestinationController)
// while sustained wind is retained here for a possible future toggle. Defaults
// to 0 so it applies cleanly to existing rows ahead of the next weather re-fetch,
// which will overwrite every row with a real value.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spot_sailable_days', function (Blueprint $table) {
            $table->decimal('qualifying_gust_kts', 5, 1)->default(0)->after('qualifying_wind_kts');
        });
    }

    public function down(): void
    {
        Schema::table('spot_sailable_days', function (Blueprint $table) {
            $table->dropColumn('qualifying_gust_kts');
        });
    }
};
