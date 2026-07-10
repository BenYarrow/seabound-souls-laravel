# TODO — Seabound Souls (Laravel)

Forward-looking backlog. Completed work is recorded in `docs/history/` and the `SITREP.md` roadmap, not here.

## Content curation (from the featured/content brainstorm — A #25, B #26, C #27; all shipped)
- [ ] Optional: a test locking the list-block empty/all-draft-picks contract (resolved `[]` → renders nothing) — the homepage's default state now relies on that guard (safe by construction today). From #26 review.
- [ ] Optional: cache the list-block picker `->options()` query (`Blog`/`SpotGuide` title lists) if the content library grows large — currently re-queried per admin form render. From #26 review.
- [ ] Optional: a light DOM test of the quick-nav active-on-scroll math (`SpotGuideNav`) — the section-derivation helper is unit-tested, but the scroll→active mapping is inspection-only (the headless preview dispatches no scroll/IO events). From #27 review. Deep-linking to `#section` on load is also a possible follow-up.

## Testing sweep
Public controllers all covered. Remaining:
- [ ] Helpers/logic units — weather-data transforms, `LiveWeatherController` caching (external HTTP + response cache)
- [ ] `Api\WeatherDataController` (index + show)
- [ ] Filament resources — smoke tests (lowest priority)

## Tooling
- [ ] CI pipeline (GitHub Actions) running `php artisan test` on every PR — **against PostgreSQL** (the suite runs on SQLite locally for speed; a Postgres CI job closes the dev/prod engine-parity gap and would have caught the Vite-dependent-suite fragility immediately). Also run the JS suite (`npm run test:js`, Vitest — added #24) and a Linux `npm run build`.
- [ ] Add `.nvmrc` pinning Node 22 so the correct version is auto-selected
- [ ] Set up husky + lint-staged + eslint-plugin-jsdoc pre-commit enforcement (JSDoc-on-every-function rule)
- [ ] Case-sensitivity guard for `@/…` imports — macOS resolves mismatched-case paths (e.g. `@/helpers` → tracked `@/Helpers`) but the case-sensitive Linux/Cloud build fails with `vite:load-fallback ENOENT` (bit us on the first deploy, fixed in #18). Add `eslint-plugin-import` case-sensitive resolution, or a Linux CI `npm run build`, so it fails locally not on deploy.

## Authoring UX
- [ ] **Draft / live preview** — let the owner preview content that isn't live yet: unpublished (draft) Pages / Blogs / Spot Guides, brand-new unsaved records, and *edited-but-unsaved* changes, without publishing. Today the public controllers hard-filter `is_published`, so drafts 404 and there's no way to see edits before they go live. Needs scoping (signed preview URLs / a Filament "Preview" action that renders the Inertia page with the in-progress form state; how to feed unsaved edits through to the front end). Raised by Ben 2026-07-10 — discuss in more detail before designing.

## Frontend
- [ ] Match the single-spot `chartColors` trio (wind/gust/temp on spot-guide pages, `resources/js/Helpers/colours.ts`) to the new muted generated family, so single-spot and multi-destination charts share one look. Left out of the destinations light-theme work (#24) to keep scope tight.
- [ ] Dark-mode token layer (CSS vars on `:root` / `html.dark`), no-flash theme switch, CI colour-guard test — sweep includes `SearchPanel` (currently raw `bg-white`/`gray-*`). Would eventually subsume the hardcoded light utilities added for the destinations light theme (#24).
- [ ] Full responsive audit across mobile / tablet / desktop breakpoints
- [ ] `CoverImage`: add an `eager` prop and use it for above-the-fold mastheads (currently `loading="lazy"` on all covers — LCP cost on heroes)
- [ ] Focal points: multi-select (gallery/slider) focal-set UI (single-select preview only today); focal `fetch()` failure feedback/rollback; prune/retype unused `Card.tsx`
- [ ] Media pipeline (post-launch): Spatie conversions (sizes + WebP + responsive `srcset`) + optional Cloudflare CDN/R2 — image perf (no AWS)

## Backend hardening
- [ ] Dispatch `FetchSpotWeatherJob` from the `SpotGuide::created` hook with `->afterCommit()` so the auto-fetch stays correct even if the Filament panel later enables `->databaseTransactions()`. Correct today (panel has no DB transactions, so the row is committed before dispatch), but the guarantee is currently implicit. Note: a naive `->afterCommit()` breaks the dispatch test under `RefreshDatabase`'s wrapping transaction — needs test-config care.
- [ ] Remove the one-off SQLite→Postgres migration tooling once the migration is proven and no longer needed: the `sqlite_legacy` connection in `config/database.php`, the `db:import-from-sqlite` command, and the stale `database/database.sqlite` file.
- [ ] Dependency advisories: 4 low-severity, **dev-only** advisories remain (`symfony/dom-crawler` XXE, `symfony/yaml` ReDoS / Billion-Laughs) — no fix published in the 7.4.x constraint window; revisit on a future `composer update`.
- [ ] Soft-delete/slug reuse: deleting a spot guide / blog / page soft-deletes it, so its unique slug stays reserved and recreating with the same slug fails with a confusing "already been taken". Improve the message (point to Restore / permanent-delete) — applies to SpotGuide, Blog, Page.

## Single-admin security (PRE-LAUNCH — required)
The single owner login has been hardened with a strong env-driven password (set at deploy-time via `AdminUserSeeder`) and panel access restricted to the owner email only. What remains is optional 2FA and production environment configuration:
- [ ] Keep Filament **registration off** (already off — never add `->registration()`).
- [ ] Optional: enable **2FA** (Filament two-factor plugin) on the admin account.
- [ ] **Production environment (deploy-time, not code):**
  - `APP_ENV=production`, `APP_DEBUG=false` (no stack-trace/query leakage)
  - `SESSION_SECURE_COOKIE=true`, HTTPS enforced
  - `QUEUE_CONNECTION=database` with a supervised worker process running (weather triggers)
  - Real transactional mail configured (see Project B)

## Project B — go-live (separate effort)
- [ ] Deploy Laravel to a host (first-ever deploy) + point `seaboundsouls.co.uk` at it, off the old Vercel holding page
- [ ] Real transactional email (Resend/Postmark) + verify sending domain (SPF/DKIM/DMARC) — then swap `MAIL_MAILER` from Herd's catcher; contact-enquiry notifications go live with no code change
