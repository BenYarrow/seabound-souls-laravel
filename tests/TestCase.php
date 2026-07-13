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
     * Create an owner account (role owner, email matching config('admin.email'))
     * and act as them, satisfying both the panel gate and owner-only policies.
     */
    protected function actingAsOwner(): User
    {
        $owner = User::factory()->create([
            'email' => config('admin.email'),
            'role' => User::ROLE_OWNER,
        ]);
        $this->actingAs($owner);

        return $owner;
    }

    /**
     * Create a rider account and act as them. Optionally override attributes
     * (e.g. a specific email) for the caller's assertions.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function actingAsRider(array $attributes = []): User
    {
        $rider = User::factory()->create(array_merge(['role' => User::ROLE_RIDER], $attributes));
        $this->actingAs($rider);

        return $rider;
    }
}
