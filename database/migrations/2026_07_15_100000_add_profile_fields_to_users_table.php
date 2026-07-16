<?php

// Public profile fields for contributor users (sub-project 2). All nullable and
// only populated for contributors; owners are unaffected. Images reference the
// centralised media_library by FK. profile_blocks is the content builder; socials
// is a small platform→URL map. `users` has no soft-deletes, so slug is a plain
// unique column (the model generates collision-safe values).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique();
            $table->foreignId('profile_image_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->foreignId('static_masthead_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->json('profile_blocks')->nullable();
            $table->json('socials')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('profile_image_media_id');
            $table->dropConstrainedForeignId('static_masthead_media_id');
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'profile_blocks', 'socials']);
        });
    }
};
