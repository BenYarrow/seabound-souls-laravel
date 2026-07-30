---
title: Rebrand to Seabound Sessions
tags: [branding, seo, admin, frontend, launch]
status: stable
updated: 2026-07-30
completed: 2026-07-30
commits: [e125192, 9750d6b, 2f20b31]
pr: 44
---

# Rebrand — Seabound Souls → Seabound Sessions

The site is renamed **Seabound Sessions** and will launch on **seaboundsessions.com**
(domain bought via Namecheap). This PR moves every brand-facing surface in the
codebase; the bulk of the *content* had already been updated by hand in the admin.

## What shipped

### Logo + favicon

The original logo (`public/images/logo.png`) is a hand-drawn circular badge with
`SEABOUND SOULS` lettered around the ring and `@SEABOUND.SOULS` around the foot.
Raster art can't be re-lettered, so the mark was **derived** from it instead:

- Measured ink density by radius to find the gap between the sunburst rays and the
  ring lettering (a clear trough at r≈510–530 in the 1620px original).
- Masked at r=524 and cropped → `public/images/logo-mark.png`: the central
  windsurfer + sun + waves + full sunburst, no lettering, nothing clipped mid-stroke.
- Nav and footer now pair that mark with a Knewave "Seabound Sessions" wordmark.

`public/favicon.png` was a **2870×1617** wordmark image — wrong aspect ratio and
illegible at 32px. Rebuilt at 512×512 from the inner medallion only (r=425, rays
excluded — they turn to noise at favicon size) on brand teal `rgb(7,98,121)`.

**The original `logo.png` is deliberately retained.** It is the source art the mark
was cropped from, and it is no longer referenced anywhere in the app.

### Brand strings

Swapped across `app.tsx`'s title template, `NavBar`, `Footer`, `Homepage` fallbacks,
`SpotGuide`/`Destinations` bylines, `ContributorController`, `TagController`,
`ContactController`, the contributor set-password page, and `AdminUserSeeder`'s
owner display name (the house "Written by" byline).

**Not touched, by decision:** social handles (`@seabound_souls`, `@seaboundsouls`),
the `seabound.souls@outlook.com` owner mailbox, the `seabound_souls*` database
names, the repo directory, and dated records under `docs/history/`. These aren't
brand-facing; see the "Brand" note in `CLAUDE.md` for the standing rule.

### Email removal

The footer's "Get in touch" mailbox row and the contact page's `mailto:` were both
removed at Ben's request — the contact form is now the single route in, and a
published address only invited scraping. `faEnvelope` imports dropped from both.

### Admin panel branding

Filament defaults `brandName` to `config('app.name')`. That made the admin chrome
hostage to `APP_NAME`: `"Laravel"` on a fresh clone, and the pre-rename name in any
environment where `APP_NAME` lagged. Now set **explicitly** on the panel, with the
rebuilt favicon:

```php
->brandName('Seabound Sessions')
->favicon(asset('favicon.png'))
```

`AdminPanelBrandingTest` pins this, including a test that asserts the brand name
survives `config(['app.name' => 'Something Else'])` — the env-drift case is the one
worth guarding, since it fails silently and only in environments nobody is looking at.

**No `brandLogo`.** The logo mark is white line art and is invisible against
Filament's light topbar. A logo here would need a dark-background variant first.

### Masthead title sizes

Hero titles were dominating the viewport. Reduced ~30% across both components:

| Layout | Before | After |
|---|---|---|
| `StaticMasthead` editorial (standard pages) | `clamp(3.5rem, 11vw, 9rem)` | `clamp(2.5rem, 7.5vw, 6rem)` |
| `MastheadSlider` (homepage) | `clamp(3.5rem, 11vw, 9rem)` | `clamp(2.5rem, 7.5vw, 6rem)` |
| `StaticMasthead` centred (spot guides) | `clamp(3rem, 9vw, 7rem)` | `clamp(2.25rem, 6.5vw, 5rem)` |

### Responsive nav fix (caught in verification)

"Seabound Sessions" is materially longer than "Seabound Souls", and the nav wordmark
is `whitespace-nowrap`. At `text-2xl` it overran the search and menu buttons on a
375px viewport. Fixed with a responsive step-down
(`text-base sm:text-xl md:text-2xl`) and a slightly smaller mobile mark
(`size-[44px] md:size-[60px]`).

Measured rather than eyeballed — at 320px the brand's right edge now sits at 212px
against a first-button left edge of 236px. An earlier `text-lg` attempt still
overlapped by 10px at 330px, which a desktop-only check would have shipped.

## Findings worth keeping

**Filament brand name should never be inherited from `APP_NAME` on a branded site.**
The failure is silent and environment-specific: local looks right while a fresh
clone or a lagging environment shows the wrong name to whoever logs in there.

**A longer brand name is a responsive regression.** Any `whitespace-nowrap` brand
element needs re-checking at the narrow end after a rename. This one was invisible
at desktop width and only appeared under a real 375px check.

**reCAPTCHA and Mapbox are domain-bound and will break at the domain swap.** Google
site keys are registered per-domain and Mapbox tokens can carry URL restrictions;
both fail *after* cutover, not before, so they read as "the new domain is broken".
Both auto-include subdomains, so registering the apex covers `www` — but not the
reverse. New keys for both were issued during this work (see TODO).

**`APP_URL` on Laravel Cloud is injected, not editable**, and is derived from the
environment's domain — confirmed live when renaming the Cloud application updated
`APP_URL` automatically. Custom variables override injected ones, so an override is
possible but likely unnecessary once the custom domain is primary. Signed
contributor invite links embed the host in their signature, so `APP_URL` must be
correct *before* any invite is minted.

## Test plan

- `php artisan test` — **256 passed** (3 new: `AdminPanelBrandingTest`).
- `npm run test:js` — 34 passed.
- `npx tsc --noEmit` — no new errors in touched files (pre-existing
  `fslightbox-react` typings + `Contact.tsx` recaptcha error-shape noise remain).
- Browser-verified at 320 / 375 / 1440px: nav mark + wordmark, footer (no mailto),
  contact page (no mailto), masthead sizes, admin login brand, both logo images
  loading.

## Follow-ups

Production-side items this PR cannot do from code are captured in
[`docs/TODO.md`](../TODO.md) under "Rebrand + launch". The headline ones: three
content records still carry the old name in the prod DB, the owner account's
display name needs re-seeding, and `public/robots.txt` advertises a sitemap at the
now-renamed Cloud vanity host.
