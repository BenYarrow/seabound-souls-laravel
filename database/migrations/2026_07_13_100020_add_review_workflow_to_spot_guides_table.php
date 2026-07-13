<?php

// Review lifecycle for the rider contribution workflow. is_published stays the
// live-visibility switch (owner-only); review_status tracks where a guide sits in
// the draft -> in_review -> changes_requested -> approved loop.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->string('review_status')->default('draft')->after('is_featured');
            $table->text('review_note')->nullable()->after('review_status');
            $table->timestamp('submitted_at')->nullable()->after('review_note');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->dropColumn(['review_status', 'review_note', 'submitted_at', 'reviewed_at']);
        });
    }
};
