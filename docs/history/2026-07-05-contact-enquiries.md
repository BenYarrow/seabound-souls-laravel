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

## Process
Spec `docs/superpowers/specs/2026-07-05-contact-enquiries-design.md`; plan `docs/superpowers/plans/2026-07-05-contact-enquiries.md`. Subagent-driven (implementer + reviewer per task, opus final review — clean).
