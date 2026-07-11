<?php

// Feature tests for the db:pull-from-production command's safety guards.
// The happy path shells out to pg_dump/pg_restore against real Postgres, so it
// is verified manually; these tests cover the guards that must hold before any
// external work — proving the command can't run in CI/production and fails
// clearly when production credentials are absent.

namespace Tests\Feature\Console;

use Tests\TestCase;

class PullFromProductionTest extends TestCase
{
    public function test_it_refuses_to_run_outside_the_local_environment(): void
    {
        // The test environment is 'testing', so the local-only guard must block.
        $this->artisan('db:pull-from-production')
            ->expectsOutputToContain('local environment')
            ->assertFailed();
    }

    public function test_it_aborts_when_production_credentials_are_missing(): void
    {
        $this->app['env'] = 'local';
        config(['database.connections.pgsql_prod.host' => null]);

        $this->artisan('db:pull-from-production')
            ->expectsOutputToContain('PROD_DB')
            ->assertFailed();
    }
}
