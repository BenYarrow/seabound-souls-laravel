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
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->json('content_blocks')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->json('seo_keywords')->nullable();
            $table->unsignedBigInteger('thumbnail_media_id')->nullable();
            $table->unsignedBigInteger('static_masthead_media_id')->nullable();
            $table->unsignedBigInteger('og_image_media_id')->nullable();
            $table->json('masthead_slider_media_ids')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('thumbnail_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('static_masthead_media_id')->references('id')->on('media_library')->nullOnDelete();
            $table->foreign('og_image_media_id')->references('id')->on('media_library')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
