<?php

// Contact page + form handler:
//   GET  /contact — contact       (renders the form)
//   POST /contact — contact.store (validates, verifies reCAPTCHA, records the
//                   enquiry, then emails a notification)

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormMail;
use App\Models\ContactEnquiry;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    /** Render the contact form, passing the reCAPTCHA site key for the widget. */
    public function index(): Response
    {
        $page = Page::where('slug', 'contact')->where('is_published', true)->with('ogImageMedia')->first();

        return Inertia::render('Contact', [
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'meta' => [
                'title' => $page?->seo_title ?: 'Contact',
                'description' => $page?->seo_description ?: 'Get in touch with the Seabound Souls team.',
                'keywords' => $page?->seo_keywords ?? [],
                'og_image' => $page?->ogImageMedia?->getUrl() ?: '',
            ],
        ]);
    }

    /**
     * Handle a contact submission. ContactFormRequest validates the input first;
     * we then server-side verify the reCAPTCHA token with Google (bailing back
     * with an error if it fails), persist the enquiry, and finally email the message
     * to the site inbox.
     */
    public function store(ContactFormRequest $request): RedirectResponse
    {
        // Server-side reCAPTCHA check — the client token alone can't be trusted.
        $recaptchaResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $request->input('recaptcha_token'),
        ]);

        if (! $recaptchaResponse->json('success')) {
            return back()->withErrors(['recaptcha' => 'reCAPTCHA verification failed.']);
        }

        // Persist the enquiry before notifying, so it's captured even if mail fails.
        $data = $request->validated();

        ContactEnquiry::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
        ]);

        Mail::to(config('mail.to.address', 'hello@seaboundsouls.com'))
            ->send(new ContactFormMail($data));

        return back()->with('success', 'Your message has been sent. We\'ll be in touch soon!');
    }
}
