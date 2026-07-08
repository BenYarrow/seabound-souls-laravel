# TODO — Seabound Souls (Laravel)

Forward-looking backlog. Completed work is recorded in `docs/history/` and the `SITREP.md` roadmap, not here.

## Testing sweep
Public controllers all covered. Remaining:
- [ ] Helpers/logic units — weather-data transforms, `LiveWeatherController` caching (external HTTP + response cache)
- [ ] `Api\WeatherDataController` (index + show)
- [ ] Filament resources — smoke tests (lowest priority)

## Tooling
- [ ] CI pipeline (GitHub Actions) running `php artisan test` on every PR — would have caught the Vite-dependent-suite fragility immediately
- [ ] Add `.nvmrc` pinning Node 22 so the correct version is auto-selected
- [ ] Set up husky + lint-staged + eslint-plugin-jsdoc pre-commit enforcement (JSDoc-on-every-function rule)

## Frontend
- [ ] Dark-mode token layer (CSS vars on `:root` / `html.dark`), no-flash theme switch, CI colour-guard test — sweep includes `SearchPanel` (currently raw `bg-white`/`gray-*`)
- [ ] Full responsive audit across mobile / tablet / desktop breakpoints
- [ ] `CoverImage`: add an `eager` prop and use it for above-the-fold mastheads (currently `loading="lazy"` on all covers — LCP cost on heroes)
- [ ] Focal points: multi-select (gallery/slider) focal-set UI (single-select preview only today); focal `fetch()` failure feedback/rollback; prune/retype unused `Card.tsx`
- [ ] Media pipeline (post-launch): Spatie conversions (sizes + WebP + responsive `srcset`) + optional Cloudflare CDN/R2 — image perf (no AWS)

## Backend hardening
- [ ] Rate-limit `/api/search` (and the other `/api/*` endpoints) before production — first typing-driven endpoint, so higher request volume; add `throttle:` to the api middleware group

## Single-admin security (PRE-LAUNCH — required)
The site is already single-login-only: the only auth is the Filament admin (`/admin`), with **no registration/password-reset and no public user system** (one user: the owner). Before launch, harden that single account:
- [ ] **Strong production admin password — not the dev credential.** Set it via an env-driven `AdminUserSeeder` (`User::updateOrCreate` from `ADMIN_EMAIL`/`ADMIN_PASSWORD` host secrets, run on deploy) so the password lives only in the host env + a password manager, never in git. Rotatable by changing the env + re-seeding.
- [ ] **Restrict the panel to the owner** via `User::canAccessPanel()` (owner email only) — so even a stray account can never reach `/admin`. (Currently any authenticated user could; only one exists today. Stakes: contact enquiries store visitor PII.)
- [ ] Keep Filament **registration off** (already off — never add `->registration()`).
- [ ] Optional: enable **2FA** (Filament two-factor plugin) on the admin account.
- [ ] Prod env basics: `APP_DEBUG=false`, `APP_ENV=production`, HTTPS enforced.

## Project B — go-live (separate effort)
- [ ] Deploy Laravel to a host (first-ever deploy) + point `seaboundsouls.co.uk` at it, off the old Vercel holding page
- [ ] Real transactional email (Resend/Postmark) + verify sending domain (SPF/DKIM/DMARC) — then swap `MAIL_MAILER` from Herd's catcher; contact-enquiry notifications go live with no code change
