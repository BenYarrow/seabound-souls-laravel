---
title: Contact enquiries — in-app inbox with email notification
tags: [contact, enquiries, filament, mail, admin]
status: stable
completed: 2026-07-05
commits: [09fcc70, b290bda, 8b71485, 446f709, 9190e3b, 4bca0f6]
pr: 9
---

# Contact enquiries

The contact form previously only emailed (and, with `MAIL_MAILER=log`, emailed nowhere) — submissions were never recorded. Now every submission is captured in the app and managed from a Filament admin inbox, with the email demoted to a notification. Built brainstorm → spec → plan → subagent-driven execution.

## What shipped
- **`contact_enquiries` table + `ContactEnquiry` model + factory** — name, email, message, `status` (`new`/`handled`), `handled_at`.
- **`ContactController@store` persists before notifying** — creates the enquiry from validated data, *then* sends `ContactFormMail`. Deliberately **not** wrapped in a transaction, so a mail failure leaves the enquiry safely recorded (wrapping would roll it back).
- **`ContactEnquiryResource` (Filament)** — read-only inbox (`canCreate()` false, no edit page): table with status badge, status filter, view page (infolist), `markHandled`/`markNew` toggle actions, and a **nav badge counting unhandled enquiries**.
- Header comment on the controller updated to reflect the new persist step.

## Findings worth keeping
- **Mass-assignment is safe despite `status`/`handled_at` being fillable** — the controller only passes validated `name`/`email`/`message` into `create()`, never the raw request, so a submitter can't set status.
- **XSS-safe** — the email view uses escaped `{{ }}`; Filament `TextColumn`/`TextEntry` render escaped text by default.
- **Local mail** goes to Herd's built-in catcher (`mailserve` on `127.0.0.1:2525`, `MAIL_MAILER=smtp`) — visible in the Herd app's Mail tab. `.env` is local-only.

## Test plan
`php artisan test` → 53 passed, 339 assertions. New: `ContactEnquiryTest` (2), `ContactControllerTest` persistence (3), `ContactEnquiryResourceTest` (3 — list, mark-handled, badge). Admin click-through is human-verified (panel needs a login).

## Deferred
- **Project B (go-live):** deploy Laravel, point `seaboundsouls.co.uk` at it (off the old Vercel holding page), real transactional email + DNS (SPF/DKIM/DMARC). This feature then needs only a `MAIL_MAILER` swap.
- **Reply-out / two-way conversation** in the admin — out of scope by decision.
- **Restrict Filament panel access** (`canAccessPanel`) — pre-existing (any authenticated user can reach `/admin`); stakes rose now that enquiries store visitor PII. Added to backlog.

## Post-review fixes (found when Ben first ran it on Herd)
- **Dev DB not migrated.** The suite runs on in-memory SQLite (auto-migrated by `RefreshDatabase`), so `php artisan test` was green while the real `database.sqlite` never got the `contact_enquiries` table — the admin badge query and the form's `create()` both 500'd until `php artisan migrate` was run on the dev DB. **Gotcha: after adding a migration, run `php artisan migrate` against the dev database — passing tests don't create dev tables.**
- **Vite CSS entry (pre-existing, unrelated bug, fixed here).** `app.blade.php` listed `resources/css/app.css` as a separate `@vite` entry, but it isn't a Vite input (`app.tsx` imports it), so it was missing from the build manifest and `npm run build` / production 500'd with "Unable to locate file in Vite manifest". Dev mode hid it (dev server serves files on demand). Fixed to `@vite(['resources/js/app.tsx'])`; the entry's bundled CSS is emitted automatically. Verified `.test` loads in build mode with no dev server.
- **Contact form never actually submitted (two more latent bugs).** (1) **No reCAPTCHA integration** — `recaptchaSiteKey` was passed to the page but never used, so `recaptcha_token` was always empty and the required-validation failed *silently* (the template only displayed `errors.recaptcha`, not the `errors.recaptcha_token` validation key). Wired up reCAPTCHA **v3** (invisible; a new v3 key pair was created — the old keys were v2 Tickbox) and made errors visible. (2) **Mail `$message` collision** — `emails/contact.blade.php` used `{{ $message }}`, but `$message` is reserved in Mailable views (the `Illuminate\Mail\Message` object), so the notification email 500'd on render (enquiry saved first, so it survived). Renamed to `$messageBody`; added a test that *renders* the mailable (`Mail::fake` never renders, which is why the faked test was green). Verified end-to-end: submit → "Message sent" → enquiry in admin, email in Herd. **Production note:** the v3 site/secret keys must be set as env vars on deploy (Project B); optionally add a v3 score threshold in `ContactController` (currently accepts any valid token).

## Process
Spec `docs/superpowers/specs/2026-07-05-contact-enquiries-design.md`; plan `docs/superpowers/plans/2026-07-05-contact-enquiries.md`. Subagent-driven (implementer + reviewer per task, opus final review — clean).
