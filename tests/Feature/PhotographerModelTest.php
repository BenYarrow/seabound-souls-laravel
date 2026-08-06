<?php

// Photographer model behaviour: slug auto-generation, the derived public-page
// gate, and credit-link resolution. creditPayload() must never produce a URL it
// cannot stand behind — every failure path degrades to a name with no link.

namespace Tests\Feature;

use App\Models\Photographer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_name_when_blank(): void
    {
        $photographer = Photographer::create(['name' => 'Hamish McTavish']);

        $this->assertSame('hamish-mctavish', $photographer->slug);
    }

    public function test_explicit_slug_is_not_overwritten(): void
    {
        $photographer = Photographer::create(['name' => 'Hamish McTavish', 'slug' => 'hamish']);

        $this->assertSame('hamish', $photographer->slug);
    }

    public function test_slug_is_reusable_after_soft_delete(): void
    {
        Photographer::create(['name' => 'Hamish'])->delete();

        $replacement = Photographer::create(['name' => 'Hamish']);

        $this->assertSame('hamish', $replacement->slug);
    }

    public function test_has_public_page_is_false_without_profile_blocks(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => null]);

        $this->assertFalse($photographer->hasPublicPage());
    }

    public function test_has_public_page_is_false_with_empty_profile_blocks(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => []]);

        $this->assertFalse($photographer->hasPublicPage());
    }

    public function test_has_public_page_is_true_with_profile_blocks(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create();

        $this->assertTrue($photographer->hasPublicPage());
    }

    public function test_with_public_page_scope_returns_only_live_records(): void
    {
        $live = Photographer::factory()->withPublicPage()->create();
        Photographer::factory()->create(['profile_blocks' => null]);

        $results = Photographer::withPublicPage()->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($live));
    }

    public function test_credit_payload_resolves_the_active_social_url(): void
    {
        $photographer = Photographer::factory()->create([
            'name' => 'Hamish',
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'instagram',
        ]);

        $this->assertSame(
            ['name' => 'Hamish', 'url' => 'https://instagram.com/hamish'],
            $photographer->creditPayload()
        );
    }

    public function test_credit_payload_has_no_url_when_the_target_key_is_empty(): void
    {
        $photographer = Photographer::factory()->create([
            'socials' => ['instagram' => ''],
            'credit_link' => 'instagram',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }

    public function test_credit_payload_has_no_url_for_an_unrecognised_key(): void
    {
        $photographer = Photographer::factory()->create([
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'myspace',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }

    public function test_credit_payload_has_no_url_when_set_to_none(): void
    {
        $photographer = Photographer::factory()->create([
            'socials' => ['instagram' => 'https://instagram.com/hamish'],
            'credit_link' => 'none',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }

    public function test_credit_payload_resolves_profile_to_the_public_page(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create([
            'slug' => 'hamish',
            'credit_link' => 'profile',
        ]);

        $this->assertSame('/photographers/hamish', $photographer->creditPayload()['url']);
    }

    public function test_credit_payload_refuses_profile_when_the_page_is_not_live(): void
    {
        // Guards the case where credits were pointed at the profile and the
        // owner later emptied the content builder — never link to a 404.
        $photographer = Photographer::factory()->create([
            'slug' => 'hamish',
            'profile_blocks' => null,
            'credit_link' => 'profile',
        ]);

        $this->assertNull($photographer->creditPayload()['url']);
    }
}
