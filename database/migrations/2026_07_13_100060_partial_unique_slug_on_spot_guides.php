<?php

// Soft-deleted spot guides kept their slug locked: the plain unique index counts
// trashed rows, so a deleted guide's slug (and title) could never be reused.
// Replace it with a PARTIAL unique index enforcing uniqueness only among live
// rows (deleted_at IS NULL). The `CREATE UNIQUE INDEX ... WHERE` syntax works on
// both Postgres (dev/prod) and SQLite (tests). Live-row uniqueness is preserved.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->dropUnique(['slug']);
        });

        DB::statement('CREATE UNIQUE INDEX spot_guides_slug_active_unique ON spot_guides (slug) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX spot_guides_slug_active_unique');

        Schema::table('spot_guides', function (Blueprint $table) {
            $table->unique('slug');
        });
    }
};
