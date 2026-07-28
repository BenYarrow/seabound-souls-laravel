<?php
// database/migrations/2026_07_28_100000_create_spot_sailable_days_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spot_sailable_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_guide_id')->constrained('spot_guides')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            // The day's 2nd-highest sailing-window (9am-7pm) sustained-wind hour, in kts.
            // A day is "sailable" at minimum X iff this value >= X (>= 2 hours at/above X).
            $table->decimal('qualifying_wind_kts', 5, 1);
            $table->timestamps();

            $table->unique(['spot_guide_id', 'date']);
            $table->index(['spot_guide_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spot_sailable_days');
    }
};
