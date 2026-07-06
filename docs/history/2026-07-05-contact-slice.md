---
title: Contact test + comment slice
tags: [testing, contact, mail, recaptcha]
status: stable
completed: 2026-07-05
commits: [9157333]
pr: 6
---

# Contact test + comment slice

Fifth slice of the up-to-speed sweep. Test coverage + comment pass for the `/contact` page and form handler — the only slice with a write action, external HTTP, and outbound mail.

## What shipped
- **`ContactControllerTest`** (5): renders with reCAPTCHA site key, requires all fields, rejects invalid email, fails when reCAPTCHA verification fails (no mail sent), sends `ContactFormMail` on success.
- Comments: module header + PHPDoc + why-comment on `ContactController`.

## Findings worth keeping
- **Test pattern for external deps established.** reCAPTCHA's Google `siteverify` call is stubbed with `Http::fake(['www.google.com/recaptcha/*' => ...])`; mail is caught with `Mail::fake()` + `Mail::assertSent()`. Neither touches the network. This is the template for any future controller that calls an external API or sends mail.
- **Validation is enforced by `ContactFormRequest`** (name/email/message/recaptcha_token all required) — so the store body only runs on already-valid input; the reCAPTCHA check is the second gate before mail is sent.

## Test plan
`php artisan test` → 34 passed, 249 assertions. `/contact` renders (200).

## Follow-ups
See `docs/TODO.md`. Remaining: Pages (catch-all), Homepage, then helper units (`LiveWeatherController` caching, weather-data transforms) and Filament smoke tests.
