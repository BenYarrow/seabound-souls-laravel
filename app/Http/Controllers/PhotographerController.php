<?php

// Public photographer profile: GET /photographers/{slug}. Renders the
// photographer's content-builder page, bio and socials. Visibility is DERIVED
// from having profile content — a photographer who only wanted an image credit
// has no page, so unknown slugs and content-free records both 404.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\Photographer;
use Inertia\Inertia;
use Inertia\Response;

class PhotographerController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render a live photographer profile, or 404 if the page isn't public.
     *
     * @param  string  $slug  the photographer's URL slug
     */
    public function show(string $slug): Response
    {
        $photographer = Photographer::where('slug', $slug)->firstOrFail();
        abort_unless($photographer->hasPublicPage(), 404);

        $photographer->load(['thumbnailMedia', 'staticMastheadMedia']);

        // Only non-empty socials reach the front end (blank/absent keys hidden).
        $socials = collect($photographer->socials ?? [])
            ->filter(fn ($url) => filled($url))
            ->all();

        return Inertia::render('Photographers/Show', [
            'photographer' => [
                'name' => $photographer->name,
                'bio' => $photographer->bio,
                'thumbnail' => $photographer->thumbnailMedia?->imagePayload(),
                'socials' => $socials,
                'profile_blocks' => $this->resolveContentBlockMedia($photographer->profile_blocks ?? []),
            ],
            'static_masthead' => $photographer->staticMastheadMedia?->imagePayload(),
            'meta' => [
                'title' => $photographer->seo_title ?: "{$photographer->name} — Seabound Sessions",
                'description' => $photographer->seo_description ?: "Photography by {$photographer->name}.",
                'keywords' => [],
                'og_image' => $photographer->thumbnailMedia?->getUrl() ?: '',
            ],
        ]);
    }
}
