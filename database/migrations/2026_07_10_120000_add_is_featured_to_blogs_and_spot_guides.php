<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Adds a per-type "featured" flag to blogs and spot guides. Single-featured is
// enforced at the model layer (HasSingleFeatured), not by a DB constraint.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published');
        });

        Schema::table('spot_guides', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_published');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', fn (Blueprint $table) => $table->dropColumn('is_featured'));
        Schema::table('spot_guides', fn (Blueprint $table) => $table->dropColumn('is_featured'));
    }
};
