# Contact Enquiries — design

**Date:** 2026-07-05
**Branch:** `feat/contact-enquiries`
**Status:** approved design → implementation

## Problem

The contact form currently only emails (`ContactController@store` → `Mail::to(...)->send(ContactFormMail)`) and persists nothing. If mail bounces, is filtered, or isn't configured, the enquiry is lost with no record. Ben wants submissions captured **in the app** so they can be read and managed from the Filament admin, with the email demoted to a notification.

## Goals

- Persist every submission to the database — never lose an enquiry.
- View and manage enquiries in the Filament admin (an in-app inbox).
- Keep the email as a notification on top.
- Fully testable/usable locally (Herd + its mail catcher); no production/domain dependency.

## Non-goals (deferred)

- **Reply-out from the admin** and **two-way conversation threading** — explicitly out of scope (reply from a normal email client for now).
- **Project B — going live:** deploying Laravel, pointing `seaboundsouls.co.uk` at it, real transactional email + DNS (SPF/DKIM/DMARC). Separate future effort; this feature just needs its mail driver swapped at that point.

## Local environment (Task 0 — DONE)

- App served by Herd at `https://seaboundsouls.test`; `APP_URL` set accordingly.
- Mail routed to Herd's built-in catcher (`mailserve` on `127.0.0.1:2525`) via `MAIL_MAILER=smtp` — notification emails render in the Herd app's Mail tab. (`.env` is gitignored / local-only.)

## Design

### A. Data model — `contact_enquiries`

Migration + `App\Models\ContactEnquiry`:

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `name` | string | from the form |
| `email` | string | from the form |
| `message` | text | from the form |
| `status` | string (enum-like) | `new` \| `handled`, default `new` |
| `handled_at` | timestamp nullable | set when marked handled |
| `created_at` / `updated_at` | timestamps | `created_at` = received time |

Lean by design — no IP/user-agent (reCAPTCHA already guards spam; easy to add later). Model casts `handled_at` to datetime; `$fillable` for name/email/message/status/handled_at. A `scopeNew()` for the unread badge count.

### B. Capture — update `ContactController@store`

Order matters: **persist first, then notify.**
1. Validation (`ContactFormRequest`) — unchanged.
2. reCAPTCHA verification — unchanged (fail → back with error, no row created).
3. `ContactEnquiry::create([...])` from `$request->validated()` (name/email/message).
4. Send `ContactFormMail` as before (notification). Mail failure must not lose the enquiry — the row already exists.

### C. Admin — `ContactEnquiryResource` (Filament)

- URL `/admin/contact-enquiries`. Label "Enquiries" (or "Contact Enquiries").
- **Table:** name, email, message (truncated), `status` badge (New = highlighted colour, Handled = muted), created_at ("received") — newest first, `status` filter.
- **View page / infolist:** full read-only details (name, email, message, received, status). No create/edit forms — enquiries only arrive via the public form.
- **Actions:** "Mark handled" (sets `status=handled`, `handled_at=now()`) and "Mark as new" (reverse); row `delete`.
- **Nav unread badge:** `getNavigationBadge()` = count of `new` enquiries, badge colour warning/primary — so the count shows in the admin sidebar.

### D. Email = notification only

Keep `ContactFormMail` → `config('mail.to.address')`. Locally visible in Herd's Mail tab. Delivers for real once Project B lands (one-line `.env` mailer swap).

## Testing (TDD)

- **`ContactControllerTest`** (extend existing):
  - Valid submission **creates one `contact_enquiry` row** with the submitted name/email/message and `status=new`, **and** still sends `ContactFormMail` (`Mail::fake`).
  - Failed reCAPTCHA → **no row created**, no mail sent.
  - Validation failure → no row created.
- **`ContactEnquiryResource` test** (Filament + Livewire testing):
  - The list page renders and shows a seeded enquiry.
  - The "mark handled" action sets `status=handled` and `handled_at`.
  - `getNavigationBadge()` returns the count of `new` enquiries.
- All external deps mocked; suite stays green (`php artisan test`).

## Files touched

- `database/migrations/xxxx_create_contact_enquiries_table.php` (new)
- `app/Models/ContactEnquiry.php` (new)
- `app/Http/Controllers/ContactController.php` (persist before notify)
- `app/Filament/Resources/ContactEnquiryResource.php` (+ generated Pages) (new)
- `tests/Feature/ContactControllerTest.php` (extend)
- `tests/Feature/Filament/ContactEnquiryResourceTest.php` (new)

## Rollout note

When Project B happens, this feature needs only: switch `MAIL_MAILER` to the transactional provider + verify the sending domain. No code change.
