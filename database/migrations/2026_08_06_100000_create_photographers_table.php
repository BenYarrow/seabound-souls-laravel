<?php

// Photographers credited for supplied imagery. Standalone (no auth): a credit is
// not an account. Slug uniqueness is enforced only among live rows via a PARTIAL
// unique index so a soft-deleted photographer's slug can be reused — mirrors the
// tags/spot_guides pattern. `CREATE UNIQUE INDEX ... WHERE` works on both
// Postgres (dev/prod) and SQLite (tests).
//
// user_id is reserved for a future login and is not read by anything today; it
// exists so granting a photographer an account later needs no migration.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photographers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->json('socials')->nullable();
            $table->string('credit_link')->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('thumbnail_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->foreignId('static_masthead_media_id')->nullable()->constrained('media_library')->nullOnDelete();
            $table->json('profile_blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX photographers_slug_active_unique ON photographers (slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX photographers_slug_active_unique');
        Schema::dropIfExists('photographers');
    }
};
