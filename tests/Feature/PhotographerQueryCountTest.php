<?php

// Credits are read from a relation on every image, so a page of cards could
// trivially become one query per card. MediaLibrary declares $with =
// ['photographer'], which batches the load; this test fails if that is removed.

namespace Tests\Feature;

use App\Models\Country;
use App\Models\MediaLibrary;
use App\Models\Photographer;
use App\Models\SpotGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PhotographerQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_destinations_page_does_not_issue_a_query_per_credited_image(): void
    {
        $country = Country::factory()->create();

        foreach (range(1, 8) as $index) {
            $photographer = Photographer::factory()->create();
            $thumbnail = MediaLibrary::create([
                'name' => "Shot {$index}",
                'photographer_id' => $photographer->id,
            ]);

            SpotGuide::withoutEvents(fn () => SpotGuide::create([
                'title' => "Spot {$index}",
                'slug' => "spot-{$index}",
                'country_id' => $country->id,
                'thumbnail_media_id' => $thumbnail->id,
                'is_published' => true,
                'published_at' => now(),
            ]));
        }

        DB::enableQueryLog();
        $this->get('/destinations')->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // Measured: 17 queries with MediaLibrary::$with = ['photographer'] in
        // place, 24 with it removed (one lazy photographer lookup per credited
        // spot, minus one already resolved elsewhere). A ceiling of 35 (the
        // original brief value) never trips, so it was tightened to 20 — well
        // clear of the batched baseline (17) but below the regressed count (24)
        // — so this guard actually fails if $with is removed.
        $this->assertLessThan(20, $queryCount, "Destinations issued {$queryCount} queries — check MediaLibrary::\$with.");
    }
}
