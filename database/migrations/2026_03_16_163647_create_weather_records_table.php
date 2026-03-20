<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weather_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_guide_id')->constrained('spot_guides')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('avg_temp', 5, 1);
            $table->decimal('kts_wind', 5, 1);
            $table->decimal('kts_gust', 5, 1);
            $table->unsignedSmallInteger('mph_wind');
            $table->unsignedSmallInteger('mph_gust');
            $table->unsignedSmallInteger('kph_wind');
            $table->unsignedSmallInteger('kph_gust');
            $table->timestamps();

            $table->unique(['spot_guide_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weather_records');
    }
};
