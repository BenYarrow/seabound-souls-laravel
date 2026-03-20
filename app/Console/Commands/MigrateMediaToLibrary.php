<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\MediaLibrary;
use App\Models\Page;
use App\Models\Recommendation;
use App\Models\SpotGuide;
use App\Models\WindsurfingLocation;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MigrateMediaToLibrary extends Command
{
    protected $signature = 'media:migrate-to-library';

    protected $description = 'Migrate existing Spatie media records to the centralised media_library table';

    public function handle(): int
    {
        $this->migrateSpotGuides();
        $this->migrateBlogs();
        $this->migratePages();
        $this->migrateRecommendations();
        $this->migrateWindsurfingLocations();

        $this->info('Media migration complete.');

        return self::SUCCESS;
    }

    private function migrateSpotGuides(): void
    {
        $this->info('Migrating SpotGuide media...');

        $spotGuides = SpotGuide::withTrashed()->with('media')->get();

        $singleCollections = [
            'thumbnail'          => 'thumbnail_media_id',
            'static-masthead'    => 'static_masthead_media_id',
            'og-image'           => 'og_image_media_id',
            'wind-conditions-bg' => 'wind_conditions_bg_media_id',
            'water-conditions-bg' => 'water_conditions_bg_media_id',
            'travelling-to-bg'   => 'travelling_to_bg_media_id',
            'lessons-and-hire-bg' => 'lessons_and_hire_bg_media_id',
        ];

        foreach ($spotGuides as $spotGuide) {
            $updates = [];

            foreach ($singleCollections as $collection => $column) {
                $media = $spotGuide->getMedia($collection)->first();
                if ($media) {
                    $ml = $this->reparentMedia($media);
                    $updates[$column] = $ml->id;
                }
            }

            $galleryMedia = $spotGuide->getMedia('gallery');
            if ($galleryMedia->isNotEmpty()) {
                $galleryIds = $galleryMedia->map(fn ($m) => $this->reparentMedia($m)->id)->values()->toArray();
                $updates['gallery_media_ids'] = json_encode($galleryIds);
            }

            if (!empty($updates)) {
                $spotGuide->updateQuietly($updates);
            }
        }

        $this->info("  → {$spotGuides->count()} spot guides processed.");
    }

    private function migrateBlogs(): void
    {
        $this->info('Migrating Blog media...');

        $blogs = Blog::withTrashed()->with('media')->get();

        $singleCollections = [
            'thumbnail'       => 'thumbnail_media_id',
            'static-masthead' => 'static_masthead_media_id',
            'og-image'        => 'og_image_media_id',
        ];

        foreach ($blogs as $blog) {
            $updates = [];

            foreach ($singleCollections as $collection => $column) {
                $media = $blog->getMedia($collection)->first();
                if ($media) {
                    $ml = $this->reparentMedia($media);
                    $updates[$column] = $ml->id;
                }
            }

            $sliderMedia = $blog->getMedia('masthead-slider');
            if ($sliderMedia->isNotEmpty()) {
                $sliderIds = $sliderMedia->map(fn ($m) => $this->reparentMedia($m)->id)->values()->toArray();
                $updates['masthead_slider_media_ids'] = json_encode($sliderIds);
            }

            if (!empty($updates)) {
                $blog->updateQuietly($updates);
            }
        }

        $this->info("  → {$blogs->count()} blogs processed.");
    }

    private function migratePages(): void
    {
        $this->info('Migrating Page media...');

        $pages = Page::withTrashed()->with('media')->get();

        $singleCollections = [
            'thumbnail'       => 'thumbnail_media_id',
            'static-masthead' => 'static_masthead_media_id',
            'og-image'        => 'og_image_media_id',
        ];

        foreach ($pages as $page) {
            $updates = [];

            foreach ($singleCollections as $collection => $column) {
                $media = $page->getMedia($collection)->first();
                if ($media) {
                    $ml = $this->reparentMedia($media);
                    $updates[$column] = $ml->id;
                }
            }

            $sliderMedia = $page->getMedia('masthead-slider');
            if ($sliderMedia->isNotEmpty()) {
                $sliderIds = $sliderMedia->map(fn ($m) => $this->reparentMedia($m)->id)->values()->toArray();
                $updates['masthead_slider_media_ids'] = json_encode($sliderIds);
            }

            if (!empty($updates)) {
                $page->updateQuietly($updates);
            }
        }

        $this->info("  → {$pages->count()} pages processed.");
    }

    private function migrateRecommendations(): void
    {
        $this->info('Migrating Recommendation media...');

        $recommendations = Recommendation::withTrashed()->with('media')->get();

        foreach ($recommendations as $recommendation) {
            $media = $recommendation->getMedia('thumbnail')->first();
            if ($media) {
                $ml = $this->reparentMedia($media);
                $recommendation->updateQuietly(['thumbnail_media_id' => $ml->id]);
            }
        }

        $this->info("  → {$recommendations->count()} recommendations processed.");
    }

    private function migrateWindsurfingLocations(): void
    {
        $this->info('Migrating WindsurfingLocation media...');

        $locations = WindsurfingLocation::with('media')->get();

        foreach ($locations as $location) {
            $media = $location->getMedia('thumbnail')->first();
            if ($media) {
                $ml = $this->reparentMedia($media);
                $location->updateQuietly(['thumbnail_media_id' => $ml->id]);
            }
        }

        $this->info("  → {$locations->count()} windsurfing locations processed.");
    }

    private function reparentMedia(Media $media): MediaLibrary
    {
        $ml = MediaLibrary::create(['name' => $media->name]);

        $media->model_type = MediaLibrary::class;
        $media->model_id = $ml->id;
        $media->collection_name = 'file';
        $media->save();

        return $ml;
    }
}
