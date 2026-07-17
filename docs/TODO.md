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

## Dev tooling — pull production DB (follow-ups)
`db:pull-from-production` shipped (#31, DB-only). Remaining:
- [ ] `--with-media` flag: sync the R2 media objects down to local disk so pulled prod images render locally (needs read-only R2 access key/secret). Today pulled rows reference R2 paths absent locally, so those images 404 in local dev.
- [ ] Optional: upgrade the local Postgres *server* from 16 → 17 to match prod, which silences the benign `SET transaction_timeout` notice pg_restore emits when a PG17 dump lands on a PG16 server (data restores fine regardless).

## Authoring UX
- [ ] **Draft / live preview (Pages & Blogs + unsaved edits)** — logged-in preview of unpublished **Spot Guides** shipped for the owner/author (2026-07-13, contributor workflow — a Filament "Preview" action + owner/author-gated `@show` + banner). Still to do: the same for **Pages / Blogs**, and previewing *edited-but-unsaved* form state (the harder part — feeding in-progress form data to the front end). Their public controllers still hard-filter `is_published`.

## Contributor workflow (follow-ups)
Sub-project 1 shipped 2026-07-13 (see history + SITREP). Remaining:
- [ ] **Email delivery** — wire the `mail` channel so contributor **invite links** and **workflow notifications** (submitted / published / changes-requested) send by email. Built email-ready (Laravel notification classes on the `database` channel; the invite link is shown in-panel to copy today). Depends on real transactional mail (see Project B).
- [ ] On Cloud/staging: set **`MAPBOX_TOKEN`** (map picker won't render without it) and confirm **`APP_URL`** matches the domain (signed set-password invite links are built from it).

### Contributor profiles (sub-project 2 — shipped 2026-07-15, #36; follow-ups)
- [ ] **On Cloud post-deploy:** drop the **"Contributor roll-up"** content block onto the About page's content builder (the `about-us`→`about` slug rename runs via migration; the block auto-lists public-profile contributors).
- [ ] **Team feedback pass** on the profile page + spot-guide author-card design (Ben is sharing for review) — revisit styling if needed.
- [ ] Optional: switch `/contributors` → `/crew` (one named-route change + a 301 redirect once a public domain exists) — pending a naming decision.
- [ ] A Livewire `fillForm(...)->call('save')` test for the My Profile page (current tests cover the policy gate + model persistence, not the page's form round-trip).
- [ ] Optional: a per-slug 301 when a contributor/tag is retired (keeps inbound links alive once the site is public/indexed).
- [ ] Optional: a dedicated notification for "contributor edited a live guide" (today an approved-guide edit reuses `GuideSubmittedForReview` when it auto-flags back to review).

## Blog tags (follow-ups)
Shipped 2026-07-14 (crawlable `/blog/tags/{slug}` pages, tag bar, post chips, owner-only admin, sitemap). Remaining polish (all Minor, none blocking — from the whole-branch review):
- [ ] **Visual eyeball** the new gradient masthead fallback, now the site-wide default for every image-less page (`StaticMasthead` no-image branch — full-viewport deep-teal gradient + glows + wave motif). Never seen live (browser preview tooling was unresponsive at build time). Check across: blog index/tag/hub, search, generic pages, homepage (no-image), and especially a **SpotGuide with no masthead image** (centred title + SpotOverview over the gradient — a central vignette was added for contrast; confirm it reads well at wide + mobile widths). Plus the tag bar/chips. These reuse raw colour utilities, so they inherit the blog's light-only state; the dark-mode token-layer sweep below will subsume that.
- [ ] Consolidate the duplicated post-projection shape (`{id,title,slug,published_at,thumbnail,seo_description}`) used in `BlogController` (×2) + `TagController` into one helper (e.g. a `Blog` card accessor).
- [ ] Consolidate `formatDate`: `Tag.tsx` + `Index.tsx` define a local copy while `Show.tsx` imports the shared `@/Helpers/helpers` one.
- [ ] Minor: `Tag::publishedBlogs()` duplicates `blogs()`'s query; redundant `->map()` after `->get([...])` in `BlogController@index` tags; sitemap runs one query per tag (N+1, cached, fine at small scale); `TagResource` shares `navigationSort=3` with `PageResource` (harmless — alphabetical tie-break orders it correctly).

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
- [ ] Soft-delete/slug reuse for **Blog & Page** — a soft-deleted record's unique slug stays reserved, so recreating with the same slug fails. Fixed for **SpotGuide** (2026-07-13, partial unique index `WHERE deleted_at IS NULL` + validation scoped to non-trashed); apply the same to Blog & Page (background task queued).

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
- [ ] When the custom domain goes live, update the `Sitemap:` URL in `public/robots.txt` to `https://seaboundsouls.co.uk/sitemap.xml` (currently points at the laravel.cloud URL). The sitemap itself is a dynamic cached route (`/sitemap.xml`, no cron) whose URLs already adapt to the serving host — only the static robots.txt line is host-specific. Also submit the sitemap in Google Search Console.
- [ ] Real transactional email (Resend/Postmark) + verify sending domain (SPF/DKIM/DMARC) — then swap `MAIL_MAILER` from Herd's catcher; contact-enquiry notifications go live with no code change
