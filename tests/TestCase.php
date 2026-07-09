<?php

// Base test case for the suite. Applies RefreshDatabase so every test runs
// against a freshly-migrated in-memory SQLite schema (see phpunit.xml) — without
// this, DB-touching tests fail with "no such table" because migrations never run.

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Inertia pages render the root Blade view, which invokes @vite. Stubbing
        // Vite here keeps the suite independent of the dev server / build manifest —
        // otherwise tests 500 with "Unable to locate file in Vite manifest" whenever
        // `npm run dev` isn't running and public/hot is absent.
        $this->withoutVite();
    }

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
}
