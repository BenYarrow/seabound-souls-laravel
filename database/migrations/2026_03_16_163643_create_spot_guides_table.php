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
        Schema::create('spot_guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('timezone')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->longText('introduction_text')->nullable();
            $table->json('spot_overview')->nullable();
            $table->json('water_conditions')->nullable();
            $table->json('wind_conditions')->nullable();
            $table->longText('when_to_go')->nullable();
            $table->longText('where_to_stay_intro')->nullable();
            $table->longText('where_to_eat_intro')->nullable();
            $table->json('travelling_to')->nullable();
            $table->json('lessons_and_hire')->nullable();
            $table->json('content_blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->unsignedBigInteger('thumbnail_media_id')->nullable();
            $table->unsignedBigInteger('static_masthead_media_id')->nullable();
            $table->unsignedBigInteger('og_image_media_id')->nullable();
            $table->unsignedBigInteger('wind_conditions_bg_media_id')->nullable();
            $table->unsignedBigInteger('water_conditions_bg_media_id')->nullable();
            $table->unsignedBigInteger('travelling_to_bg_media_id')->nullable();
            $table->unsignedBigInteger('lessons_and_hire_bg_media_id')->nullable();
            $table->json('gallery_media_ids')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('thumbnail_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('static_masthead_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('og_image_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('wind_conditions_bg_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('water_conditions_bg_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('travelling_to_bg_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('lessons_and_hire_bg_media_id')->references('id')->on('media_library')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spot_guides');
    }
};
