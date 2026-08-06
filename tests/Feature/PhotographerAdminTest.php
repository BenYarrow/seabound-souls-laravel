<?php

// The photographer admin is owner-only: contributors author guides, they do not
// manage the site's photography credits.

namespace Tests\Feature;

use App\Models\Photographer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotographerAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_list_photographers(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);

        $this->actingAs($owner)->get('/admin/photographers')->assertOk();
    }

    public function test_contributor_cannot_list_photographers(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);

        $this->actingAs($contributor)->get('/admin/photographers')->assertForbidden();
    }

    public function test_credit_link_options_exclude_profile_until_the_page_is_live(): void
    {
        $photographer = Photographer::factory()->create(['profile_blocks' => null]);

        $this->assertArrayNotHasKey('profile', \App\Filament\Forms\PhotographerProfileForm::creditLinkOptions($photographer));
    }

    public function test_credit_link_options_include_profile_once_the_page_is_live(): void
    {
        $photographer = Photographer::factory()->withPublicPage()->create();

        $this->assertArrayHasKey('profile', \App\Filament\Forms\PhotographerProfileForm::creditLinkOptions($photographer));
    }
}
