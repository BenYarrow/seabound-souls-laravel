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
}
