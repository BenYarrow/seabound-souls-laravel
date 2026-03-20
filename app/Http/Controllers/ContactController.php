<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactFormRequest;
use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Contact', [
            'recaptchaSiteKey' => config('services.recaptcha.site_key'),
            'meta' => [
                'title' => 'Contact | Seabound Souls',
                'description' => 'Get in touch with the Seabound Souls team.',
            ],
        ]);
    }

    public function store(ContactFormRequest $request): RedirectResponse
    {
        // Verify reCAPTCHA
        $recaptchaResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $request->input('recaptcha_token'),
        ]);

        if (! $recaptchaResponse->json('success')) {
            return back()->withErrors(['recaptcha' => 'reCAPTCHA verification failed.']);
        }

        Mail::to(config('mail.to.address', 'hello@seaboundsouls.com'))
            ->send(new ContactFormMail($request->validated()));

        return back()->with('success', 'Your message has been sent. We\'ll be in touch soon!');
    }
}
