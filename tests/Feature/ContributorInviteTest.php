<?php

// Covers the invited-contributor set-password flow: a valid signed link renders the
// form, an unsigned link is rejected, and posting the form sets the password
// and logs the contributor straight into the panel.

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ContributorInviteTest extends TestCase
{
    public function test_signed_link_renders_the_set_password_form(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $url = URL::temporarySignedRoute('contributor.password.setup', now()->addDays(7), ['user' => $contributor->id]);

        $this->get($url)->assertOk()->assertSee('Set your password', false);
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);

        $this->get(route('contributor.password.setup', ['user' => $contributor->id]))->assertForbidden();
    }

    public function test_posting_sets_password_and_logs_in(): void
    {
        $contributor = User::factory()->create(['role' => User::ROLE_CONTRIBUTOR]);
        $url = URL::temporarySignedRoute('contributor.password.store', now()->addDays(7), ['user' => $contributor->id]);

        // Start a session and pin the request to it via an explicit (unencrypted)
        // session cookie, so the POST resumes the *same* session id instead of
        // the test client silently minting a fresh one (which would make any
        // before/after id comparison meaningless). EncryptCookies is disabled
        // for this request only so the raw id round-trips without needing the
        // app's real encrypter to wrap it first.
        $this->startSession();
        $oldSessionId = session()->getId();

        $response = $this->withoutMiddleware(\Illuminate\Cookie\Middleware\EncryptCookies::class)
            ->withUnencryptedCookie(session()->getName(), $oldSessionId)
            ->post($url, [
                'password' => 'new-secret-pw',
                'password_confirmation' => 'new-secret-pw',
            ]);

        $response->assertRedirect('/admin');
        $this->assertTrue(Hash::check('new-secret-pw', $contributor->fresh()->password));
        $this->assertAuthenticatedAs($contributor->fresh());

        // Session fixation guard (CWE-384): login must rotate the session id
        // rather than keep authenticating the pre-existing session as-is.
        $this->assertNotSame($oldSessionId, session()->getId());
    }
}
