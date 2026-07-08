<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            // Focal point as percentages (0–100); 50/50 = centre. Applied via CSS
            // object-position so cropped displays keep the subject in frame.
            $table->unsignedTinyInteger('focal_x')->default(50)->after('folder');
            $table->unsignedTinyInteger('focal_y')->default(50)->after('focal_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->dropColumn(['focal_x', 'focal_y']);
        });
    }
};
