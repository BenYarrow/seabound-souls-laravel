# Single-admin security hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Set the production admin password from an environment variable and restrict the Filament panel to the owner email, without changing local dev login.

**Architecture:** A new `config/admin.php` holds the owner email + password, sourced from `ADMIN_EMAIL`/`ADMIN_PASSWORD` env vars with dev-default fallbacks. `User` implements `FilamentUser` and gates `canAccessPanel` on `config('admin.email')`. An `AdminUserSeeder` uses `updateOrCreate` (keyed on email) so re-seeding rotates the password. The existing Filament tests move to an `actingAsOwner()` helper so they authenticate as a panel-eligible user under the new gate.

**Tech Stack:** Laravel 12, Filament 3.3, PHPUnit 11 (in-memory SQLite).

## Global Constraints

- **`config('admin.*')` is the only path to these values at runtime.** `env('ADMIN_EMAIL')` / `env('ADMIN_PASSWORD')` may be read **only** inside `config/admin.php` — never in `User`, seeders, or tests. (Reading `env()` at runtime returns `null` once `config:cache` runs in production.)
- **Local dev defaults (unchanged login):** email `seabound.souls@outlook.com`, password `password`.
- **Use `updateOrCreate`, not `firstOrCreate`** — so a changed `ADMIN_PASSWORD` rotates the password on re-seed.
- **Filament contracts:** `Filament\Models\Contracts\FilamentUser`, `Filament\Panel`.
- **2FA is out of scope** (deferred to its own branch).
- Tests run on in-memory SQLite; `php artisan test` needs no server and no Node.
- The suite must be green at each task's final commit — the `canAccessPanel` gate and the Filament-test fix land together (Task 1).

---

## File Structure

- `config/admin.php` — owner email + password (env-sourced, dev fallbacks). New.
- `app/Models/User.php` — implements `FilamentUser` + `canAccessPanel`.
- `tests/TestCase.php` — new `actingAsOwner()` helper.
- `tests/Feature/Auth/PanelAccessTest.php` — `canAccessPanel` gate tests. New.
- `tests/Feature/Filament/*.php` — swap `actingAs(User::factory()->create())` → `$this->actingAsOwner()` (7 call sites, 5 files).
- `database/seeders/AdminUserSeeder.php` — env-driven owner seeder. New.
- `database/seeders/DatabaseSeeder.php` — call `AdminUserSeeder` instead of inline `firstOrCreate`.
- `tests/Feature/Database/AdminUserSeederTest.php` — seed + rotation tests. New.
- `.env.example`, `CLAUDE.md`, `docs/TODO.md` — docs.

---

### Task 1: Owner-only panel gate

**Files:**
- Create: `config/admin.php`
- Modify: `app/Models/User.php`
- Create: `tests/Feature/Auth/PanelAccessTest.php`
- Modify: `tests/TestCase.php`
- Modify: `tests/Feature/Filament/PageResourceContentBuilderTest.php`, `SpotGuideCoordinatesTest.php`, `MediaPickerFocalTest.php`, `ContactEnquiryResourceTest.php`, `WeatherFetchWidgetTest.php`

**Interfaces:**
- Produces: `config('admin.email')` / `config('admin.password')`; `User::canAccessPanel(\Filament\Panel): bool`; `TestCase::actingAsOwner(): \App\Models\User`.

- [ ] **Step 1: Write the failing gate test**

Create `tests/Feature/Auth/PanelAccessTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Tests\TestCase;

/**
 * The Filament panel is gated to the single owner account (config('admin.email')).
 * Any other authenticated user must be refused, even though only the owner
 * exists today — the gate is defence-in-depth for the contact-enquiry PII.
 */
class PanelAccessTest extends TestCase
{
    public function test_owner_email_can_access_the_admin_panel(): void
    {
        config(['admin.email' => 'owner@example.com']);
        $owner = User::factory()->create(['email' => 'owner@example.com']);

        $this->assertTrue($owner->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_non_owner_email_cannot_access_the_admin_panel(): void
    {
        config(['admin.email' => 'owner@example.com']);
        $other = User::factory()->create(['email' => 'intruder@example.com']);

        $this->assertFalse($other->canAccessPanel(Filament::getPanel('admin')));
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=PanelAccessTest`
Expected: FAIL — `User::canAccessPanel()` does not exist (and `config('admin.email')` is null).

- [ ] **Step 3: Create `config/admin.php`**

```php
<?php

// Single owner admin account. These are the ONLY places ADMIN_EMAIL /
// ADMIN_PASSWORD env vars may be read — everything else reads config('admin.*')
// so the values survive `config:cache` in production. Unset locally → dev
// defaults (local login unchanged); set both in production (Laravel Cloud).

return [
    'email'    => env('ADMIN_EMAIL', 'seabound.souls@outlook.com'),
    'password' => env('ADMIN_PASSWORD', 'password'),
];
```

- [ ] **Step 4: Implement `FilamentUser` on `User`**

In `app/Models/User.php`, add the imports (after the existing `use` lines) and the interface + method:

```php
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
```

Change the class declaration:

```php
class User extends Authenticatable implements FilamentUser
```

Add this method inside the class (after `casts()`):

```php
    /**
     * Gate Filament panel access to the single owner account. Config-driven so
     * it survives `config:cache` in production — do not read env() here.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->email === config('admin.email');
    }
```

- [ ] **Step 5: Run the gate test — now passes, but the Filament suite breaks**

Run: `php artisan test --filter=PanelAccessTest`
Expected: PASS.

Run: `php artisan test --filter=Filament`
Expected: FAIL — the existing Filament tests act as a random-email factory user who is now denied the panel. Fixed in the next steps.

- [ ] **Step 6: Add the `actingAsOwner()` helper to the base TestCase**

In `tests/TestCase.php`, add the import and the helper method:

```php
use App\Models\User;
```

Add inside the class (after `setUp()`):

```php
    /**
     * Create the owner user (email matching config('admin.email')) and act as
     * them, so panel tests satisfy User::canAccessPanel's owner-only gate.
     */
    protected function actingAsOwner(): User
    {
        $owner = User::factory()->create(['email' => config('admin.email')]);
        $this->actingAs($owner);

        return $owner;
    }
```

- [ ] **Step 7: Swap the Filament tests to `actingAsOwner()`**

In each of these files, replace every occurrence of:

```php
$this->actingAs(User::factory()->create());
```

with:

```php
$this->actingAsOwner();
```

Files + counts: `PageResourceContentBuilderTest.php` (1), `SpotGuideCoordinatesTest.php` (1), `MediaPickerFocalTest.php` (2), `ContactEnquiryResourceTest.php` (1), `WeatherFetchWidgetTest.php` (2).

After the swap, if `User` is no longer referenced anywhere else in a given file, remove its now-unused `use App\Models\User;` import from that file. (Check each file; do not remove the import from a file that still uses `User` elsewhere.)

- [ ] **Step 8: Run the full suite**

Run: `php artisan test`
Expected: PASS — the 2 new gate tests plus the whole existing suite (previously 96) green.

- [ ] **Step 9: Commit**

```bash
git add config/admin.php app/Models/User.php tests/TestCase.php tests/Feature/Auth/PanelAccessTest.php tests/Feature/Filament/
git commit -m "feat: gate Filament panel to the owner email (config-driven)"
```

---

### Task 2: Env-driven admin seeder

**Files:**
- Create: `database/seeders/AdminUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `tests/Feature/Database/AdminUserSeederTest.php`

**Interfaces:**
- Consumes: `config('admin.email')` / `config('admin.password')` (Task 1).
- Produces: `Database\Seeders\AdminUserSeeder` (runnable via `php artisan db:seed --class=AdminUserSeeder`).

- [ ] **Step 1: Write the failing seeder tests**

Create `tests/Feature/Database/AdminUserSeederTest.php`:

```php
<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The owner account is seeded from config('admin.*') (env-driven). Keyed on
 * email via updateOrCreate so a changed ADMIN_PASSWORD rotates the password on
 * re-seed rather than creating a second user.
 */
class AdminUserSeederTest extends TestCase
{
    public function test_it_seeds_the_owner_from_config(): void
    {
        config(['admin.email' => 'owner@example.com', 'admin.password' => 'secret-one']);

        $this->seed(AdminUserSeeder::class);

        $user = User::where('email', 'owner@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Seabound Souls', $user->name);
        $this->assertTrue(Hash::check('secret-one', $user->password));
    }

    public function test_reseeding_rotates_the_password_without_duplicating(): void
    {
        config(['admin.email' => 'owner@example.com', 'admin.password' => 'secret-one']);
        $this->seed(AdminUserSeeder::class);

        config(['admin.password' => 'secret-two']);
        $this->seed(AdminUserSeeder::class);

        $this->assertSame(1, User::where('email', 'owner@example.com')->count());
        $this->assertTrue(
            Hash::check('secret-two', User::where('email', 'owner@example.com')->first()->password)
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php artisan test --filter=AdminUserSeederTest`
Expected: FAIL — class `Database\Seeders\AdminUserSeeder` not found.

- [ ] **Step 3: Create the seeder**

Create `database/seeders/AdminUserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds (or updates) the single owner account from config('admin.*'), which is
 * env-driven. Idempotent via updateOrCreate keyed on email: re-running after an
 * ADMIN_PASSWORD change rotates the password rather than creating a duplicate.
 * Run standalone on deploy: `php artisan db:seed --class=AdminUserSeeder`.
 */
class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::updateOrCreate(
            ['email' => config('admin.email')],
            [
                'name' => 'Seabound Souls',
                'password' => Hash::make(config('admin.password')),
            ],
        );
    }
}
```

- [ ] **Step 4: Wire it into `DatabaseSeeder`**

Replace the body of `database/seeders/DatabaseSeeder.php`'s `run()` so it calls the new seeder instead of the inline `firstOrCreate`. The full file becomes:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CountriesSeeder::class,
        ]);
    }
}
```

(Note the now-unused `use App\Models\User;` and `use Illuminate\Support\Facades\Hash;` imports are dropped in the rewrite above.)

- [ ] **Step 5: Run the seeder tests + full suite**

Run: `php artisan test --filter=AdminUserSeederTest`
Expected: PASS.

Run: `php artisan test`
Expected: PASS (whole suite).

- [ ] **Step 6: Commit**

```bash
git add database/seeders/AdminUserSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Database/AdminUserSeederTest.php
git commit -m "feat: env-driven AdminUserSeeder with password rotation"
```

---

### Task 3: Documentation

**Files:**
- Modify: `.env.example`
- Modify: `CLAUDE.md`
- Modify: `docs/TODO.md`

**Interfaces:** none (docs only, no test).

- [ ] **Step 1: Add the admin block to `.env.example`**

Find the `SESSION_DOMAIN=null` line. Immediately after it, insert a blank line then:

```
# ADMIN_EMAIL / ADMIN_PASSWORD — the single owner login for /admin.
# Leave UNSET locally to use the dev defaults (seabound.souls@outlook.com /
# "password"). In production (Laravel Cloud) set BOTH to real secret values,
# then run:  php artisan db:seed --class=AdminUserSeeder
# Re-run that after changing ADMIN_PASSWORD to rotate the password.
# ADMIN_EMAIL=
# ADMIN_PASSWORD=
```

- [ ] **Step 2: Note the auth model in `CLAUDE.md`**

Under the `## Admin Panel (Filament)` heading, immediately after the `**URL:** /admin | **Color:** Amber` line, add:

```markdown

**Auth (single owner):** the only login is the Filament admin — no registration, no password reset. Panel access is gated to the owner email via `User::canAccessPanel()` against `config('admin.email')`. The owner credentials come from `ADMIN_EMAIL`/`ADMIN_PASSWORD` (env) through `config/admin.php`, seeded by `AdminUserSeeder` (`updateOrCreate` — re-seed to rotate). Locally these are unset, so the dev default `seabound.souls@outlook.com` / `password` applies. Never read the `ADMIN_*` env vars outside `config/admin.php`.
```

- [ ] **Step 3: Update `docs/TODO.md`**

In the `## Single-admin security (PRE-LAUNCH — required)` section, **remove** the two now-shipped checklist items (the "Strong production admin password…" bullet and the "Restrict the panel to the owner via `User::canAccessPanel()`…" bullet). Leave the "Keep Filament registration off", the "Optional: enable 2FA", and the "Production environment (deploy-time…)" items in place. Update the short intro sentence of that section so it reads that the strong-password + owner-only-panel hardening has shipped and what remains is 2FA (optional) + the deploy-time production environment. Do not strike-through — delete the completed lines.

- [ ] **Step 4: Commit**

```bash
git add .env.example CLAUDE.md docs/TODO.md
git commit -m "docs: document env-driven single-owner admin auth"
```

---

## Post-implementation

Before the PR: run `reconcile-everything` on this branch (folded) — history doc `docs/history/2026-07-09-single-admin-security.md`, SITREP "right now" bullet + roadmap row, advance marker post-merge. Then open the PR; after merge, run `git-dance` and finalise the tag.

**Deploy-time (owner, on Laravel Cloud — not in this PR):** set `ADMIN_EMAIL` + `ADMIN_PASSWORD` env vars, then run `php artisan db:seed --class=AdminUserSeeder` as a deploy step. Rotate by changing `ADMIN_PASSWORD` and re-running the seeder.
