<?php

// Credits an image to a photographer. Null — the default and the overwhelming
// majority — means the image is the site's own and renders with no credit.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->foreignId('photographer_id')->nullable()->constrained('photographers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photographer_id');
        });
    }
};
