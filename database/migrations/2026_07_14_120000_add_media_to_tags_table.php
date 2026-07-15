<?php

// Adds two optional images to blog tags, both referencing the centralised
// media_library by FK (nullable — a tag renders a designed gradient fallback
// when either is absent):
//   - thumbnail_media_id: the card image shown for the tag on the /blog/tags hub
//   - static_masthead_media_id: the hero image at the top of the tag's own page

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->foreignId('static_masthead_media_id')->nullable()->constrained('media_library')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropConstrainedForeignId('thumbnail_media_id');
            $table->dropConstrainedForeignId('static_masthead_media_id');
        });
    }
};
