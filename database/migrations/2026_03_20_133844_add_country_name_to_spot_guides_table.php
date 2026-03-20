<?php

use App\Models\SpotGuide;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->string('country_name')->nullable()->after('country_id');
        });

        // Backfill from the country relationship
        SpotGuide::with('country')->get()->each(function ($guide) {
            $guide->updateQuietly(['country_name' => $guide->country?->name]);
        });
    }

    public function down(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->dropColumn('country_name');
        });
    }
};
