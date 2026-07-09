# Local dev → PostgreSQL Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Switch local development from SQLite to PostgreSQL (matching Laravel Cloud) while preserving all existing local content.

**Architecture:** Add a read-only `sqlite_legacy` connection pointing at the old SQLite file; build the Postgres schema from existing migrations; a one-off `db:import-from-sqlite` command copies content tables in FK-safe order, preserving primary keys and resetting Postgres ID sequences. The test suite stays on in-memory SQLite.

**Tech Stack:** Laravel 12, PostgreSQL 16 (Homebrew, `127.0.0.1:5432`, user `benyarrow`, trust auth), PHPUnit 11.

## Global Constraints

- **Engine:** PostgreSQL for local dev; connection name `pgsql`.
- **DB naming:** local database is **`seabound_souls_dev`**; production is `seabound_souls`.
- **`.env` is gitignored** — the actual connection switch is applied locally and is NOT committed. Only `config/database.php`, the command, and docs are committed.
- **Preserve primary keys** on transfer (FKs reference them) and **reset Postgres sequences** afterward.
- **Drivers stay on `database`** (session/cache/queue) — now backed by Postgres. Not changed in this branch.
- **Test suite stays on in-memory SQLite** (`phpunit.xml` unchanged).
- **TDD exception (approved in spec):** the cross-engine data *copy* is verified by execution (row-count parity + app rendering), not by a unit test — there is no second engine in the test harness and nothing external to mock. The command's *guard* (refuse unless pgsql) IS unit-tested.
- Content tables, in FK-safe order: `users, countries, media_library, media, spot_guides, blogs, pages, recommendations, windsurfing_locations, weather_records`.
- Not copied (transient/regenerated): `sessions, cache, cache_locks, jobs, job_batches, failed_jobs, migrations, password_reset_tokens`.

---

## File Structure

- `config/database.php` — add a `sqlite_legacy` connection block (the migration source).
- `app/Console/Commands/ImportFromSqlite.php` — new `db:import-from-sqlite` command (the transfer).
- `tests/Feature/Console/ImportFromSqliteTest.php` — guard test + config test.
- `.env.example`, `CLAUDE.md`, `docs/TODO.md` — documentation of the new local default.

---

### Task 1: `sqlite_legacy` source connection

**Files:**
- Modify: `config/database.php` (after the `sqlite` block, ~line 45)
- Test: `tests/Feature/Console/ImportFromSqliteTest.php` (create)

**Interfaces:**
- Produces: a `sqlite_legacy` DB connection resolvable via `DB::connection('sqlite_legacy')`, reading `database_path('database.sqlite')`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Console/ImportFromSqliteTest.php`:

```php
<?php

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * Covers the SQLite → Postgres migration tooling: the legacy source
 * connection and the import command's safety guard. The actual cross-engine
 * copy is verified by execution (see the plan's Task 4), not here — the test
 * harness runs on in-memory SQLite with no Postgres target to write to.
 */
class ImportFromSqliteTest extends TestCase
{
    /**
     * The legacy connection must point at the on-disk SQLite file so the
     * one-off migration can read the pre-Postgres data regardless of what
     * DB_DATABASE now points at.
     */
    public function test_sqlite_legacy_connection_points_at_the_database_file(): void
    {
        $this->assertSame('sqlite', config('database.connections.sqlite_legacy.driver'));
        $this->assertStringEndsWith(
            'database.sqlite',
            config('database.connections.sqlite_legacy.database')
        );
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=test_sqlite_legacy_connection_points_at_the_database_file`
Expected: FAIL — `sqlite_legacy` connection is not defined (driver is null).

- [ ] **Step 3: Add the connection**

In `config/database.php`, immediately after the `sqlite` connection block (closes at ~line 45), insert:

```php
        'sqlite_legacy' => [
            'driver' => 'sqlite',
            // Fixed path to the pre-Postgres SQLite file — the migration
            // source. Deliberately NOT env-driven: DB_DATABASE now points at
            // Postgres. Remove once the one-off migration is proven (see
            // docs/TODO.md).
            'database' => database_path('database.sqlite'),
            'prefix' => '',
            'foreign_key_constraints' => false,
        ],
```

- [ ] **Step 4: Run it to verify it passes**

Run: `php artisan test --filter=test_sqlite_legacy_connection_points_at_the_database_file`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add config/database.php tests/Feature/Console/ImportFromSqliteTest.php
git commit -m "feat: add read-only sqlite_legacy connection for the Postgres migration"
```

---

### Task 2: `db:import-from-sqlite` command

**Files:**
- Create: `app/Console/Commands/ImportFromSqlite.php`
- Test: `tests/Feature/Console/ImportFromSqliteTest.php` (add a method)

**Interfaces:**
- Consumes: the `sqlite_legacy` connection from Task 1.
- Produces: an artisan command `db:import-from-sqlite` that copies content tables from `sqlite_legacy` into the default (`pgsql`) connection, preserving PKs and resetting sequences. Exit code `0` on success, `1` when the guard blocks it.

- [ ] **Step 1: Write the failing guard test**

Add this method to `tests/Feature/Console/ImportFromSqliteTest.php`:

```php
    /**
     * Safety guard: the command must refuse to run unless Postgres is the
     * default connection, so it can never fire against SQLite (or, in future,
     * a mis-targeted DB). The test env default is sqlite, so this exercises the
     * abort path directly.
     */
    public function test_import_command_refuses_to_run_without_pgsql(): void
    {
        $this->artisan('db:import-from-sqlite')
            ->expectsOutputToContain('requires the pgsql connection')
            ->assertExitCode(1);
    }
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php artisan test --filter=test_import_command_refuses_to_run_without_pgsql`
Expected: FAIL — command `db:import-from-sqlite` does not exist.

- [ ] **Step 3: Create the command**

Create `app/Console/Commands/ImportFromSqlite.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off migration tool: copies all content from the pre-Postgres SQLite file
 * (via the `sqlite_legacy` connection) into the current default connection,
 * which must be Postgres. Primary keys are preserved because foreign keys
 * across the schema reference them; Postgres ID sequences are reset afterward
 * so subsequent inserts don't collide on a duplicate PK.
 *
 * Transient framework tables (sessions, cache, jobs, migrations, …) are NOT
 * copied — they regenerate. Media files on disk are untouched; the copied
 * `media` rows reference them by UUID.
 */
class ImportFromSqlite extends Command
{
    protected $signature = 'db:import-from-sqlite';

    protected $description = 'Copy content from the legacy SQLite file into the Postgres database (one-off migration)';

    /**
     * Content tables in foreign-key-safe order: a parent is always copied
     * before any table that references it.
     */
    private const TABLES = [
        'users',
        'countries',
        'media_library',
        'media',
        'spot_guides',
        'blogs',
        'pages',
        'recommendations',
        'windsurfing_locations',
        'weather_records',
    ];

    public function handle(): int
    {
        if (DB::getDefaultConnection() !== 'pgsql') {
            $this->error('This command requires the pgsql connection to be the default (set DB_CONNECTION=pgsql).');

            return self::FAILURE;
        }

        // Guard: never overwrite an already-populated target.
        foreach (self::TABLES as $table) {
            if (DB::table($table)->count() > 0) {
                $this->error("Target table '{$table}' is not empty — aborting to avoid overwriting data.");

                return self::FAILURE;
            }
        }

        $source = DB::connection('sqlite_legacy');

        foreach (self::TABLES as $table) {
            $rows = $source->table($table)->get();

            if ($rows->isEmpty()) {
                $this->resetSequence($table, isCalled: false, value: 1);
                $this->line("  {$table}: 0 rows");

                continue;
            }

            // SQLite stores booleans as 0/1 integers; Postgres boolean columns
            // reject integers, so cast them to real PHP booleans first.
            $booleanColumns = $this->booleanColumns($table);

            $records = $rows->map(function ($row) use ($booleanColumns): array {
                $data = (array) $row;

                foreach ($booleanColumns as $column) {
                    if (array_key_exists($column, $data) && $data[$column] !== null) {
                        $data[$column] = (bool) $data[$column];
                    }
                }

                return $data;
            })->all();

            foreach (array_chunk($records, 100) as $chunk) {
                DB::table($table)->insert($chunk);
            }

            $this->resetSequence($table, isCalled: true, value: (int) DB::table($table)->max('id'));
            $this->info("  {$table}: {$rows->count()} rows copied");
        }

        $this->info('SQLite → Postgres import complete.');

        return self::SUCCESS;
    }

    /**
     * Names of the Postgres columns of type boolean for the given table.
     */
    private function booleanColumns(string $table): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('table_name', $table)
            ->where('data_type', 'boolean')
            ->pluck('column_name')
            ->all();
    }

    /**
     * Reset the table's id sequence. When the table has rows, mark the sequence
     * "called" at max(id) so the next value is max+1; when empty, set it to 1
     * "not called" so the next value is 1.
     */
    private function resetSequence(string $table, bool $isCalled, int $value): void
    {
        DB::statement(
            "SELECT setval(pg_get_serial_sequence(?, 'id'), ?, ?)",
            [$table, max($value, 1), $isCalled]
        );
    }
}
```

- [ ] **Step 4: Run it to verify the guard test passes**

Run: `php artisan test --filter=ImportFromSqliteTest`
Expected: PASS (both the config test and the guard test).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ImportFromSqlite.php tests/Feature/Console/ImportFromSqliteTest.php
git commit -m "feat: add db:import-from-sqlite migration command"
```

---

### Task 3: Documentation of the new local default

**Files:**
- Modify: `.env.example` (lines 25-30, the DB block)
- Modify: `CLAUDE.md` (session-start bullets + the "### Database" subsection)
- Modify: `docs/TODO.md` (CI item + new follow-up)

**Interfaces:** none (docs only, no test).

- [ ] **Step 1: Update `.env.example`**

Replace the current DB block (lines 25-30):

```
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

with:

```
# Local dev + production use PostgreSQL (matches Laravel Cloud). Tests run on
# in-memory SQLite (see phpunit.xml). Local convention: suffix the DB name with
# _dev (e.g. seabound_souls_dev); production uses seabound_souls.
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=seabound_souls
DB_USERNAME=postgres
DB_PASSWORD=
```

- [ ] **Step 2: Update `CLAUDE.md` — session-start prerequisite**

In the `### Session start` section, immediately after the `**Node:**` bullet, add a `**Database:**` bullet:

```markdown
- **Database:** Local dev runs on **PostgreSQL** (matches Laravel Cloud), DB `seabound_souls_dev` on `127.0.0.1:5432`. It's served by the Homebrew `postgresql@16` service (`brew services list` to check; `brew services start postgresql@16` if down) — the app 500s on a DB connection error if it isn't running. The **test suite** runs on in-memory SQLite (`phpunit.xml`), so `php artisan test` needs no Postgres.
```

- [ ] **Step 3: Update `CLAUDE.md` — the "### Database" subsection**

Find the `### Database` subsection under "Working standard" (currently just the migrations rule) and add a line above the existing migration rule:

```markdown
- **Local dev + production: PostgreSQL** (`pgsql`). Tests: in-memory SQLite. The pre-Postgres SQLite file was migrated once via `php artisan db:import-from-sqlite` (reads the `sqlite_legacy` connection). Don't reintroduce SQLite as the app's default connection.
```

- [ ] **Step 4: Update `docs/TODO.md` — refine CI item + add follow-up**

In the `## Tooling` section, replace the CI line:

```markdown
- [ ] CI pipeline (GitHub Actions) running `php artisan test` on every PR — would have caught the Vite-dependent-suite fragility immediately
```

with:

```markdown
- [ ] CI pipeline (GitHub Actions) running `php artisan test` on every PR — **against PostgreSQL** (the suite runs on SQLite locally for speed; a Postgres CI job closes the dev/prod engine-parity gap and would have caught the Vite-dependent-suite fragility immediately)
```

In the `## Backend hardening` section, add:

```markdown
- [ ] Remove the one-off SQLite→Postgres migration tooling once the migration is proven and no longer needed: the `sqlite_legacy` connection in `config/database.php`, the `db:import-from-sqlite` command, and the stale `database/database.sqlite` file.
```

- [ ] **Step 5: Commit**

```bash
git add .env.example CLAUDE.md docs/TODO.md
git commit -m "docs: document PostgreSQL as the local dev default"
```

---

### Task 4: Execute the migration and verify (operational)

**Files:** none committed — this is the local switch + verification. `.env` is gitignored.

**Interfaces:**
- Consumes: the `sqlite_legacy` connection (Task 1) and the `db:import-from-sqlite` command (Task 2).

This task is the spec's **verify-by-execution** proof. Run in the local shell (Node PATH not required — no frontend build here).

- [ ] **Step 1: Create the Postgres database**

Run: `psql -h 127.0.0.1 -U benyarrow -d postgres -c "CREATE DATABASE seabound_souls_dev OWNER benyarrow;"`
Expected: `CREATE DATABASE`.

- [ ] **Step 2: Point local `.env` at Postgres**

Edit `.env` (gitignored). Set:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=seabound_souls_dev
DB_USERNAME=benyarrow
DB_PASSWORD=
```

Then: `php artisan config:clear`
Expected: `INFO Configuration cache cleared successfully.`

- [ ] **Step 3: Build the schema**

Run: `php artisan migrate --force`
Expected: all migrations run against Postgres, ending `DONE`. (Fresh empty DB — every migration runs.)

- [ ] **Step 4: Run the import**

Run: `php artisan db:import-from-sqlite`
Expected output (row counts):

```
  users: 1 rows copied
  countries: 6 rows copied
  media_library: 14 rows copied
  media: 14 rows copied
  spot_guides: 3 rows copied
  blogs: 1 rows copied
  pages: 5 rows copied
  recommendations: 5 rows copied
  windsurfing_locations: 0 rows
  weather_records: 119 rows copied
SQLite → Postgres import complete.
```

- [ ] **Step 5: Verify row-count parity**

Run:

```bash
php artisan tinker --execute='foreach (["users","countries","media_library","media","spot_guides","blogs","pages","recommendations","windsurfing_locations","weather_records"] as $t) { echo str_pad($t,24).\DB::table($t)->count().PHP_EOL; }'
```

Expected: matches Step 4 counts (users 1, countries 6, media_library 14, media 14, spot_guides 3, blogs 1, pages 5, recommendations 5, windsurfing_locations 0, weather_records 119).

- [ ] **Step 6: Full test suite still green**

Run: `php artisan test`
Expected: all tests pass (still on in-memory SQLite; 94+ tests).

- [ ] **Step 7: App renders against Postgres**

Ensure Postgres is up and Vite is running (`composer dev` in a Node-22 shell). Then verify (curl is enough to prove no 500s; browser confirms visually):

```bash
for path in / /destinations /blog; do echo "--- $path ---"; curl -sS -o /dev/null -w "%{http_code}\n" https://seaboundsouls.test$path; done
```

Expected: `200` for each. Then in-browser check: homepage, one spot guide page, `/destinations` (wind/temp charts must render — proves `weather_records` + JSON columns survived), `/admin` loads, media picker shows thumbnails, weather widget renders.

- [ ] **Step 8: Queue write-path + sequence sanity**

- With a queue worker running (`php artisan queue:work --stop-when-empty` or `composer dev`), trigger a "Fetch all weather" from `/admin` dashboard and confirm new `weather_records` rows are written (proves the `payload LIKE` job query + write path on Postgres).
- In `/admin`, create and save a new record (e.g. a Country) and confirm no duplicate-PK error (proves sequences were reset).

- [ ] **Step 9: No commit**

Nothing to commit here — `.env` is gitignored and the DB is local. This task's output is the verification evidence above.

---

## Post-implementation

Before the PR: run `reconcile-everything` on this branch (folded) — it writes the `docs/history/2026-07-09-postgres-local-dev.md` retrospective, adds the SITREP "right now" bullet + roadmap row, and advances the marker. Then open the PR; after merge, run `git-dance`.
