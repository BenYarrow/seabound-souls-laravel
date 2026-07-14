<?php

// Curated blog tags. Slug uniqueness is enforced only among live rows via a
// PARTIAL unique index (deleted_at IS NULL) so a soft-deleted tag's slug can be
// reused — mirrors the spot_guides slug fix. `CREATE UNIQUE INDEX ... WHERE`
// works on both Postgres (dev/prod) and SQLite (tests).

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        DB::statement('CREATE UNIQUE INDEX tags_slug_active_unique ON tags (slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX tags_slug_active_unique');
        Schema::dropIfExists('tags');
    }
};
