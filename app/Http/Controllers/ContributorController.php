<?php

// Public contributor profile: GET /contributors/{slug}. Renders a contributor's
// content-builder profile, socials, and published guides. Resolves among users
// WITH a public profile (contributor + ≥1 published guide), so unknown slugs,
// owners, and not-yet-live contributors all 404 — public presence is earned by
// publishing a guide.

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesContentBlockMedia;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class ContributorController extends Controller
{
    use ResolvesContentBlockMedia;

    /**
     * Render a live contributor profile, or 404 if the profile isn't public.
     *
     * @param  string  $slug  the contributor's URL slug
     */
    public function show(string $slug): Response
    {
        $contributor = User::where('slug', $slug)->firstOrFail();
        abort_unless($contributor->hasPublicProfile(), 404);

        $contributor->load(['profileImageMedia', 'staticMastheadMedia']);

        $guides = $contributor->publishedAuthoredGuides()
            ->with(['thumbnailMedia', 'country'])
            ->latest('published_at')
            ->get()
            ->map(fn ($guide) => [
                'id' => $guide->id,
                'title' => $guide->title,
                'slug' => $guide->slug,
                'thumbnail' => $guide->thumbnailMedia?->imagePayload(),
                'country' => $guide->country?->name,
            ]);

        // Only non-empty socials reach the front end (blank/absent keys hidden).
        $socials = collect($contributor->socials ?? [])
            ->filter(fn ($url) => filled($url))
            ->all();

        return Inertia::render('Contributors/Show', [
            'contributor' => [
                'name' => $contributor->name,
                'first_name' => $contributor->first_name,
                'profile_image' => $contributor->profileImageMedia?->imagePayload(),
                'socials' => $socials,
                'profile_blocks' => $this->resolveContentBlockMedia($contributor->profile_blocks ?? []),
            ],
            'static_masthead' => $contributor->staticMastheadMedia?->imagePayload(),
            'guides' => $guides,
            'meta' => [
                'title' => "{$contributor->name} — Seabound Sessions",
                'description' => "Windsurfing spot guides and story from {$contributor->name}.",
                'keywords' => [],
                'og_image' => $contributor->profileImageMedia?->getUrl() ?: '',
            ],
        ]);
    }
}
