<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * Panel access is role-based: owners and contributors may enter the panel; a user with
 * any unrecognised role is refused. Per-resource policies (not the panel gate) do
 * the fine-grained gating of PII and house content.
 */
class PanelAccessTest extends TestCase
{
    public function test_owner_can_access_the_admin_panel(): void
    {
        $owner = User::factory()->create(['role' => User::ROLE_OWNER]);
        $this->assertTrue($owner->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_contributor_can_access_the_admin_panel(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $this->assertTrue($contributor->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_user_with_unknown_role_cannot_access_the_admin_panel(): void
    {
        $stranger = User::factory()->create(['role' => 'guest']);
        $this->assertFalse($stranger->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_role_helpers_report_correctly(): void
    {
        $this->assertTrue(User::factory()->create(['role' => User::ROLE_OWNER])->isOwner());
        $this->assertTrue(User::factory()->create(['role' => User::ROLE_CONTRIBUTOR])->isContributor());
    }
}
