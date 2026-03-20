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
        Schema::create('windsurfing_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spot_guide_id')->constrained('spot_guides')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('thumbnail_media_id')->nullable();
            $table->timestamps();

            $table->foreign('thumbnail_media_id')->references('id')->on('media_library')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('windsurfing_locations');
    }
};
