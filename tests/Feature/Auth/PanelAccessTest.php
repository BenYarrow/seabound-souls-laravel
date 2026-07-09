<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * The Filament panel is gated to the single owner account (config('admin.email')).
 * Any other authenticated user must be refused, even though only the owner
 * exists today — the gate is defence-in-depth for the contact-enquiry PII.
 */
class PanelAccessTest extends TestCase
{
    public function test_owner_email_can_access_the_admin_panel(): void
    {
        config(['admin.email' => 'owner@example.com']);
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->assertTrue($owner->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_non_owner_email_cannot_access_the_admin_panel(): void
    {
        config(['admin.email' => 'owner@example.com']);
        $other = User::factory()->create(['email' => 'intruder@example.com']);

        $this->assertFalse($other->canAccessPanel(Filament::getPanel('admin')));
    }
}
