<?php

// Covers the invited-rider set-password flow: a valid signed link renders the
// form, an unsigned link is rejected, and posting the form sets the password
// and logs the rider straight into the panel.

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RiderInviteTest extends TestCase
{
    public function test_signed_link_renders_the_set_password_form(): void
    {
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $url = URL::temporarySignedRoute('rider.password.setup', now()->addDays(7), ['user' => $rider->id]);

        $this->get($url)->assertOk()->assertSee('Set your password', false);
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);

        $this->get(route('rider.password.setup', ['user' => $rider->id]))->assertForbidden();
    }

    public function test_posting_sets_password_and_logs_in(): void
    {
        $rider = User::factory()->create(['role' => User::ROLE_RIDER]);
        $url = URL::temporarySignedRoute('rider.password.store', now()->addDays(7), ['user' => $rider->id]);

        $response = $this->post($url, [
            'password' => 'new-secret-pw',
            'password_confirmation' => 'new-secret-pw',
        ]);

        $response->assertRedirect('/admin');
        $this->assertTrue(Hash::check('new-secret-pw', $rider->fresh()->password));
        $this->assertAuthenticatedAs($rider->fresh());
    }
}
