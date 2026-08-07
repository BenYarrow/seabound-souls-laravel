<?php

// The public photographer profile (GET /photographers/{slug}). Visibility is
// derived from profile_blocks, so a photographer who only wanted a credit never
// gets a thin, empty page — and the sitemap must never advertise one.

namespace Tests\Feature;

use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_renders_for_a_photographer_with_content(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create([
            'name' => 'Hamish McTavish',
            'bio' => 'Water shots in Tarifa.',
        ]);

        $this->get('/photographers/'.$photographer->slug)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Photographers/Show')
                ->where('photographer.name', 'Hamish McTavish')
                ->where('photographer.bio', 'Water shots in Tarifa.'));
    }

    public function test_page_404s_without_profile_content(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => null]);

        $this->get('/photographers/'.$photographer->slug)->assertNotFound();
    }

    public function test_unknown_slug_404s(): void
    {
        $this->get('/photographers/nobody-here')->assertNotFound();
    }

    public function test_soft_deleted_photographer_404s(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create();
        $photographer->delete();

        $this->get('/photographers/'.$photographer->slug)->assertNotFound();
    }

    public function test_sitemap_lists_live_photographer_pages_only(): void
    {
        $live = Photographer::factory()->withPublicPage()->create(['name' => 'Live One']);
        $gated = Photographer::factory()->create(['name' => 'Gated One', 'profile_blocks' => null]);

        $response = $this->get('/sitemap.xml')->assertOk();

        $response->assertSee('/photographers/'.$live->slug);
        $response->assertDontSee('/photographers/'.$gated->slug);
    }
}
