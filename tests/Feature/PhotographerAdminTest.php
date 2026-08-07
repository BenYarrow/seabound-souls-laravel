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

    /**
     * Filament's `authorize()` helper defaults a MISSING ability on an
     * EXISTING policy to allow (see vendor/filament/filament/src/helpers.php),
     * so PhotographerPolicy must declare deleteAny/restore/restoreAny/
     * forceDelete/forceDeleteAny explicitly — PhotographerResource ships a
     * DeleteBulkAction and Photographer uses SoftDeletes, so these abilities
     * are genuinely reachable, not theoretical.
     */
    public function test_only_the_owner_can_bulk_delete_restore_or_force_delete_photographers(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $photographer = Photographer::factory()->create();

        $this->assertTrue($owner->can('deleteAny', Photographer::class));
        $this->assertTrue($owner->can('restore', $photographer));
        $this->assertTrue($owner->can('restoreAny', Photographer::class));
        $this->assertTrue($owner->can('forceDelete', $photographer));
        $this->assertTrue($owner->can('forceDeleteAny', Photographer::class));

        $this->assertFalse($contributor->can('deleteAny', Photographer::class));
        $this->assertFalse($contributor->can('restore', $photographer));
        $this->assertFalse($contributor->can('restoreAny', Photographer::class));
        $this->assertFalse($contributor->can('forceDelete', $photographer));
        $this->assertFalse($contributor->can('forceDeleteAny', Photographer::class));
    }
}
