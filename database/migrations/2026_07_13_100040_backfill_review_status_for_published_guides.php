<?php

// Data-only backfill: 2026_07_13_100020 added review_status defaulting to
// 'draft', but production already has guides published (is_published = true)
// before the review workflow existed. Left alone, those would surface a
// "Draft" status badge in the admin despite being live. This migration flips
// review_status to 'approved' for every already-published guide so the badge
// reflects reality. That already-run migration is never edited (project
// rule); this ships as its own migration instead. No-op on a fresh/test DB
// (no rows exist yet at migrate time), so it doesn't affect the test suite.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('spot_guides')
            ->where('is_published', true)
            ->update(['review_status' => 'approved']); // App\Models\SpotGuide::STATUS_APPROVED
    }

    public function down(): void
    {
        // Irreversible data backfill.
    }
};
