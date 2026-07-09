<?php

// Drops the unused `timezone` column from spot_guides. It was collected in the
// admin and passed to the front end but never rendered anywhere, and the weather
// fetch derives the timezone from coordinates (Open-Meteo `timezone=auto`), so
// the stored value served no purpose. Down re-adds the nullable column.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->dropColumn('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->string('timezone')->nullable();
        });
    }
};
