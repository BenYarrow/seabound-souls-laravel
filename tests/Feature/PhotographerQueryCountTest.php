<?php

// Credits are read from a relation on every image, so a page of cards could
// trivially become one query per card. MediaLibrary declares $with =
// ['photographer'], which batches the load; this test fails if that is
// removed.
//
// The guard asserts a *scaling invariant* rather than an absolute ceiling:
// running the same /destinations request against a small and a large amount
// of credited data must issue the SAME number of queries against the
// "photographers" table. A batched (eager) load runs once per parent query
// no matter how many credited images are on the page, so that count is flat;
// an unbatched (lazy) load runs once PER credited image, so it grows with
// the data. An absolute ceiling (e.g. "fewer than 20 queries") only ever
// narrows the gap between the batched baseline and the regressed count — the
// natural fix when it trips for an unrelated reason (a new eager load, a new
// filter query) is to raise the ceiling, and each raise quietly erodes the
// guard until it stops catching the regression it exists for. Asserting
// equality instead ties the pass/fail condition directly to the invariant we
// actually care about: photographer-lookup count independent of
// credited-image count.
//
// Why filter to the "photographers" table rather than the total query count:
// investigating this rewrite surfaced a genuine, PRE-EXISTING, UNRELATED
// per-row query — MediaLibrary::getUrl()/getThumbnailUrl() call Spatie's own
// getFirstMediaUrl(), which lazily loads that model's own `media` morph
// relation once per MediaLibrary instance touched (it is not covered by
// $with, and each request-scoped model instance has to resolve it
// individually). That query count scales 1:1 with the number of spot-guide
// thumbnails on the page regardless of the photographer relation, so a
// whole-request equality assertion would never pass even with $with intact —
// not because the photographer guard is broken, but because of that separate
// bug. Per the brief for this test, that is a real per-row query and must
// not be papered over by widening the assertion; instead this test narrows
// its scope to the table the $with guard actually governs. The `media`
// N+1 is tracked separately as its own follow-up, not fixed here.
// See docs/TODO.md.

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

    public function test_destinations_page_photographer_query_count_does_not_scale_with_credited_image_count(): void
    {
        $country = Country::factory()->create();

        // Small dataset: 2 credited spots, each with its own photographer and
        // its own credited thumbnail (so an unbatched relation really would
        // issue one query per spot).
        $this->createCreditedSpots($country, 1, 2);

        DB::enableQueryLog();
        $this->get('/destinations')->assertOk();
        $smallPhotographerQueryCount = $this->countPhotographerQueries();
        DB::disableQueryLog();
        DB::flushQueryLog();

        // Grow the dataset to 10 credited spots and measure again. Nothing
        // about the query SHAPE changes — the batched photographer load is
        // still a single `WHERE IN (...)` query — only the number of rows it
        // matches grows, which should not change the query COUNT.
        $this->createCreditedSpots($country, 3, 10);

        DB::enableQueryLog();
        $this->get('/destinations')->assertOk();
        $largePhotographerQueryCount = $this->countPhotographerQueries();
        DB::disableQueryLog();

        $this->assertSame(
            $smallPhotographerQueryCount,
            $largePhotographerQueryCount,
            "Destinations issued {$smallPhotographerQueryCount} photographer queries for 2 credited spots but {$largePhotographerQueryCount} for 10 — check MediaLibrary::\$with (photographer credits are no longer batched)."
        );
    }

    /**
     * Create credited spot guides numbered $startIndex..$endIndex (inclusive),
     * each with its own Photographer and its own credited MediaLibrary
     * thumbnail, so every credited image is a distinct row that an unbatched
     * relation would have to resolve with its own query.
     */
    private function createCreditedSpots(Country $country, int $startIndex, int $endIndex): void
    {
        foreach (range($startIndex, $endIndex) as $index) {
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
    }

    /**
     * Count queries against the "photographers" table in the current query
     * log — the table MediaLibrary::$with = ['photographer'] governs. See the
     * module header for why this is narrower than the total request query
     * count.
     */
    private function countPhotographerQueries(): int
    {
        return count(array_filter(
            DB::getQueryLog(),
            fn (array $entry) => str_contains($entry['query'], '"photographers"')
        ));
    }
}
