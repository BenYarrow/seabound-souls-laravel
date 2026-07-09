# Pre-launch Security Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the launch-blocking findings from the 2026-07-09 security audit — patch vulnerable dependencies, rate-limit public endpoints, range-validate live-weather coordinates, and document the production-config requirements.

**Architecture:** Named rate limiters defined in `AppServiceProvider::boot()` and attached to routes via `throttle:{name}` middleware; a tightened validation rule in one controller; a dependency bump verified by the suite + build; and documentation-only changes for prod config.

**Tech Stack:** Laravel 12, Filament v3.3, PHPUnit 11, SQLite in-memory (tests), `array` cache store in tests (so throttle counters reset per test), Composer, npm.

## Global Constraints

- **PHPDoc on every method** explaining *why* where non-obvious; module-header comment atop each new file.
- **No single-letter variables** (except `i` in a trivial loop); no cryptic abbreviations.
- **TDD**: failing test first, watch it fail, implement, watch it pass, commit.
- **All external I/O mocked in tests** (`Http::fake()`); no live network.
- **Rate limits (per IP, per minute):** `search` = **60**, `weather-api` = **30**, `contact` = **5**.
- **Dependency floors:** Filament **≥ 3.3.53** (latest 3.3.x; no major jump), guzzle **≥ 7.12.1**. If `composer update` moves anything beyond the named packages unexpectedly, STOP and surface it.
- **`php artisan test` must pass fully** before any task is done.
- Prod-config items are **environment, not code** — documentation only; do not add code claiming to "fix" prod env.

---

### Task 1: Named rate limiters + throttle middleware

Define three per-IP rate limiters and attach them to the public endpoints.

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (define limiters in `boot()`)
- Modify: `routes/api.php` (attach `throttle:search` / `throttle:weather-api`)
- Modify: `routes/web.php` (attach `throttle:contact` to `POST /contact`)
- Test: `tests/Feature/RateLimitingTest.php`

**Interfaces:**
- Produces: three named limiters — `search` (60/min), `weather-api` (30/min), `contact` (5/min), each keyed by `$request->ip()`, resolvable via `RateLimiter::limiter('<name>')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

// Feature tests for the public-endpoint rate limiters (search / weather-api /
// contact). Verifies the named limiters are registered, the middleware is
// attached to each route, and the limit is actually enforced (429).

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    public function test_named_limiters_are_registered(): void
    {
        $this->assertNotNull(RateLimiter::limiter('search'));
        $this->assertNotNull(RateLimiter::limiter('weather-api'));
        $this->assertNotNull(RateLimiter::limiter('contact'));
    }

    /**
     * @dataProvider throttledRoutes
     */
    public function test_route_has_throttle_middleware(string $routeName, string $expectedMiddleware): void
    {
        $route = app('router')->getRoutes()->getByName($routeName);

        $this->assertNotNull($route, "Route {$routeName} should exist");
        $this->assertContains($expectedMiddleware, $route->gatherMiddleware());
    }

    public static function throttledRoutes(): array
    {
        return [
            'search' => ['api.search', 'throttle:search'],
            'live-weather' => ['api.live-weather', 'throttle:weather-api'],
            'weather-data index' => ['api.weather-data.index', 'throttle:weather-api'],
            'weather-data show' => ['api.weather-data.show', 'throttle:weather-api'],
            'contact' => ['contact.store', 'throttle:contact'],
        ];
    }

    public function test_contact_endpoint_returns_429_past_the_limit(): void
    {
        // Empty payloads fail validation (302 back) but still pass through the
        // throttle middleware, so the 6th request within the minute is blocked.
        for ($requestNumber = 1; $requestNumber <= 5; $requestNumber++) {
            $this->post('/contact', [])->assertStatus(302);
        }

        $this->post('/contact', [])->assertStatus(429);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RateLimitingTest`
Expected: FAIL — limiters not registered (null) and no `throttle:*` middleware on the routes; the contact loop never hits 429.

- [ ] **Step 3: Define the limiters in AppServiceProvider**

Replace the body of `app/Providers/AppServiceProvider.php` `boot()`:

```php
<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Register the public-endpoint rate limiters. All are keyed by client IP.
     * Laravel 12 ships no default API throttle, so without these the endpoints
     * are unbounded — `/api/live-weather` in particular proxies OpenWeatherMap
     * with our key, and `/contact` writes a row + sends mail per hit.
     */
    public function boot(): void
    {
        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('weather-api', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
```

- [ ] **Step 4: Attach throttle middleware to the API routes**

Replace `routes/api.php`:

```php
<?php

use App\Http\Controllers\Api\LiveWeatherController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\WeatherDataController;
use Illuminate\Support\Facades\Route;

// Search suggestions are typing-driven, so they get a higher ceiling than the
// weather endpoints (which proxy an external, keyed API).
Route::get('/search', [SearchController::class, 'index'])
    ->middleware('throttle:search')
    ->name('api.search');

Route::middleware('throttle:weather-api')->group(function () {
    Route::post('/live-weather', [LiveWeatherController::class, 'fetch'])->name('api.live-weather');
    Route::get('/weather-data', [WeatherDataController::class, 'index'])->name('api.weather-data.index');
    Route::get('/weather-data/{spotGuide}', [WeatherDataController::class, 'show'])->name('api.weather-data.show');
});
```

- [ ] **Step 5: Attach throttle middleware to the contact POST**

In `routes/web.php`, change the contact store route (leave the GET as-is):

```php
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --filter=RateLimitingTest`
Expected: PASS (all cases).

- [ ] **Step 7: Run the full suite (regression on route/middleware changes)**

Run: `php artisan test`
Expected: PASS — existing api/contact tests still green (they make well under the limits per test, and the `array` cache store resets counters between tests).

- [ ] **Step 8: Commit**

```bash
git add app/Providers/AppServiceProvider.php routes/api.php routes/web.php tests/Feature/RateLimitingTest.php
git commit -m "feat: rate-limit public API and contact endpoints

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Range-validate live-weather coordinates

Bound the `lat`/`lon` inputs so the endpoint can't be driven with nonsense coordinates (and pairs with the rate limit to protect the upstream API quota).

**Files:**
- Modify: `app/Http/Controllers/Api/LiveWeatherController.php` (validation rules)
- Test: `tests/Feature/Api/LiveWeatherControllerTest.php`

**Interfaces:**
- Consumes: `throttle:weather-api` from Task 1 (already attached to the route — no change here).

- [ ] **Step 1: Write the failing test**

```php
<?php

// Feature tests for App\Http\Controllers\Api\LiveWeatherController — coordinate
// validation and the cached OpenWeatherMap proxy (mocked).

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LiveWeatherControllerTest extends TestCase
{
    public function test_rejects_out_of_range_coordinates_without_calling_the_api(): void
    {
        Http::fake();

        $this->postJson('/api/live-weather', ['lat' => 200, 'lon' => 500])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lon']);

        // Validation must fail before any outbound request is made.
        Http::assertNothingSent();
    }

    public function test_accepts_valid_coordinates_and_returns_weather(): void
    {
        Http::fake([
            'api.openweathermap.org/*' => Http::response(['weather' => [['main' => 'Clear']]], 200),
        ]);

        $this->postJson('/api/live-weather', ['lat' => 36.0, 'lon' => -6.0])
            ->assertOk()
            ->assertJsonPath('weather.0.main', 'Clear');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LiveWeatherControllerTest`
Expected: FAIL — `lat=200`/`lon=500` currently pass `numeric` validation, so the first test gets 200 (and an outbound call) instead of 422.

- [ ] **Step 3: Tighten the validation rules**

In `app/Http/Controllers/Api/LiveWeatherController.php`, change the `validate` call:

```php
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=LiveWeatherControllerTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Run the full suite**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/LiveWeatherController.php tests/Feature/Api/LiveWeatherControllerTest.php
git commit -m "feat: range-validate live-weather coordinates

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Bump vulnerable dependencies

Patch the audited vulnerabilities. Mechanical + verification; no new tests — the existing suite + build + a smoke check are the safety net.

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer update`)
- Modify: `package-lock.json` (via `npm audit fix`)

**Interfaces:** none.

- [ ] **Step 1: Record the pre-bump versions**

Run: `composer show filament/filament guzzlehttp/guzzle | grep -i versions`
Expected: notes the current versions (Filament ~3.3.49) for the commit message / rollback reference.

- [ ] **Step 2: Update the Composer packages**

Run: `composer update filament/filament guzzlehttp/guzzle guzzlehttp/psr7 --with-all-dependencies`
Expected: Filament moves to the latest **3.3.x** (≥3.3.53), guzzle to ≥7.12.1. Review the summary — if any package **outside** these and their direct deps moved, STOP and surface it before continuing.

- [ ] **Step 3: Confirm the advisories are cleared**

Run: `composer audit`
Expected: the Filament forms XSS (CVE-2026-55409), Filament temp-upload (CVE-2026-48500), and guzzle advisories no longer appear. (Any remaining low-severity transitive advisories with no fix available: note them, don't block.)

- [ ] **Step 4: Fix the npm dev advisories**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm audit fix`
Expected: vite / shell-quote / qs advisories resolved (these are dev-only — not shipped). Do NOT use `--force` (which pulls breaking major bumps); if something can't be fixed without `--force`, note it and leave it.

- [ ] **Step 5: Run the full test suite**

Run: `php artisan test`
Expected: PASS — the Filament bump is a patch line, so no test changes expected.

- [ ] **Step 6: Build the frontend + Filament theme**

Run: `export PATH="$HOME/.nvm/versions/node/v22.22.3/bin:$PATH" && npm run build`
Expected: builds clean (confirms the Filament/asset bump didn't break the theme).

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock package-lock.json
git commit -m "chore: bump Filament (XSS/temp-upload CVEs), guzzle, npm audit fix

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

*(Note for the implementer: a manual admin-panel smoke check is part of the spec's verification but requires a login the automated run doesn't have — flag it in your report for the human to do, don't block on it.)*

---

### Task 4: Production-config launch checklist (docs only)

Document the required production environment. No code — the repo cannot set the host's env.

**Files:**
- Modify: `.env.example` (annotate the prod-required values)
- Modify: `docs/TODO.md` (tighten the pre-launch prod-env checklist)

**Interfaces:** none.

- [ ] **Step 1: Annotate `.env.example`**

In `.env.example`, add a comment directly above the `APP_ENV` line (near the top):

```
# PRODUCTION: set APP_ENV=production, APP_DEBUG=false, and SESSION_SECURE_COOKIE=true
# (behind HTTPS). Leaking stack traces / running debug in prod is a security risk.
APP_ENV=local
```

And add above `SESSION_DOMAIN` (or the session block):

```
# PRODUCTION: SESSION_SECURE_COOKIE=true so the session cookie is HTTPS-only.
```

- [ ] **Step 2: Tighten the TODO prod-env checklist**

In `docs/TODO.md`, under the existing single-admin/security section's "Prod env basics" bullet, expand it to a clear checklist (replace the one-line bullet):

```markdown
- [ ] **Production environment (deploy-time, not code):**
  - `APP_ENV=production`, `APP_DEBUG=false` (no stack-trace/query leakage)
  - `SESSION_SECURE_COOKIE=true`, HTTPS enforced
  - `QUEUE_CONNECTION=database` with a supervised worker process running (weather triggers)
  - Real transactional mail configured (see Project B)
```

- [ ] **Step 3: Verify no code changed**

Run: `git status --short`
Expected: only `.env.example` and `docs/TODO.md` modified.

- [ ] **Step 4: Commit**

```bash
git add .env.example docs/TODO.md
git commit -m "docs: production environment security checklist

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-Review

**1. Spec coverage:**
- Dependency bumps (Filament/guzzle/npm) → Task 3 ✅
- Named rate limiters (search 60 / weather-api 30 / contact 5) → Task 1 ✅
- throttle middleware on `/api/*` + `/contact` → Task 1 ✅
- lat/lon `between` validation → Task 2 ✅
- Prod-config checklist + `.env.example` annotations → Task 4 ✅
- Testing: limiter registration + middleware attachment + real 429 + 422/valid → Tasks 1 & 2 ✅
- "Stop if composer moves unexpected packages" constraint → Task 3 Step 2 ✅

**2. Placeholder scan:** No TBD/TODO-as-instruction/"handle edge cases". All code + commands are complete. ✅

**3. Type consistency:** Limiter names `search` / `weather-api` / `contact` are identical across the provider (Task 1 Step 3), the routes (Steps 4–5), and the tests (Step 1 data provider). Route names (`api.search`, `api.live-weather`, `api.weather-data.index`, `api.weather-data.show`, `contact.store`) match the existing route definitions. ✅

**Note for executor:** the `contact.store` route name and `api.*` names are asserted in Task 1's test — confirm they match `routes/web.php` / `routes/api.php` before running (they do as of this plan; adjust if the routes were renamed).
