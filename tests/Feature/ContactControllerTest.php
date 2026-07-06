<?php

// Feature tests for App\Http\Controllers\ContactController — the /contact page
// and form submission. Covers rendering, validation, reCAPTCHA verification, and
// the success path. The reCAPTCHA HTTP call and outbound mail are faked so tests
// need no network.

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Models\ContactEnquiry;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    /** A valid submission payload. */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Sailor',
            'email' => 'jane@example.com',
            'message' => 'When is the best time to visit Tarifa?',
            'recaptcha_token' => 'test-token',
        ], $overrides);
    }

    /** Fake the Google reCAPTCHA siteverify endpoint with the given success flag. */
    private function fakeRecaptcha(bool $success): void
    {
        Http::fake([
            'www.google.com/recaptcha/*' => Http::response(['success' => $success]),
        ]);
    }

    public function test_index_renders_the_contact_page_with_the_recaptcha_site_key(): void
    {
        config(['services.recaptcha.site_key' => 'site-key-123']);

        $response = $this->get(route('contact'));

        $response->assertOk();
        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('Contact')
                ->where('recaptchaSiteKey', 'site-key-123')
        );
    }

    public function test_store_requires_all_fields(): void
    {
        Mail::fake();

        $response = $this->post(route('contact.store'), []);

        $response->assertSessionHasErrors(['name', 'email', 'message', 'recaptcha_token']);
        Mail::assertNothingSent();
    }

    public function test_store_rejects_an_invalid_email(): void
    {
        $response = $this->post(route('contact.store'), $this->validPayload(['email' => 'not-an-email']));

        $response->assertSessionHasErrors('email');
    }

    public function test_store_fails_when_recaptcha_verification_fails(): void
    {
        Mail::fake();
        $this->fakeRecaptcha(false);

        $response = $this->post(route('contact.store'), $this->validPayload());

        $response->assertSessionHasErrors('recaptcha');
        Mail::assertNothingSent();
    }

    public function test_store_sends_mail_when_recaptcha_passes(): void
    {
        Mail::fake();
        $this->fakeRecaptcha(true);

        $response = $this->post(route('contact.store'), $this->validPayload());

        $response->assertSessionHas('success');
        Mail::assertSent(
            ContactFormMail::class,
            fn (ContactFormMail $mail) => $mail->formData['email'] === 'jane@example.com'
        );
    }

    public function test_store_persists_the_enquiry_on_success(): void
    {
        Mail::fake();
        $this->fakeRecaptcha(true);

        $this->post(route('contact.store'), $this->validPayload([
            'name' => 'Jane Sailor',
            'email' => 'jane@example.com',
        ]));

        $this->assertDatabaseHas('contact_enquiries', [
            'name' => 'Jane Sailor',
            'email' => 'jane@example.com',
            'status' => 'new',
        ]);
        Mail::assertSent(ContactFormMail::class);
    }

    public function test_store_does_not_persist_when_recaptcha_fails(): void
    {
        Mail::fake();
        $this->fakeRecaptcha(false);

        $this->post(route('contact.store'), $this->validPayload());

        $this->assertDatabaseCount('contact_enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_store_does_not_persist_on_validation_failure(): void
    {
        $this->post(route('contact.store'), []);

        $this->assertDatabaseCount('contact_enquiries', 0);
    }

    public function test_contact_form_mail_renders_without_reserved_message_collision(): void
    {
        // Mail::fake() never renders the view, so this renders the mailable for
        // real — guarding against the reserved $message variable collision that
        // 500'd in production while the faked test stayed green.
        $mail = new ContactFormMail([
            'name' => 'Jane Sailor',
            'email' => 'jane@example.com',
            'message' => 'Rendering check for the message body.',
            'recaptcha_token' => 'token',
        ]);

        $rendered = $mail->render();

        $this->assertStringContainsString('Rendering check for the message body.', $rendered);
        $this->assertStringContainsString('jane@example.com', $rendered);
    }
}
