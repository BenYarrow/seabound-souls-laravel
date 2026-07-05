<?php

// Base test case for the suite. Applies RefreshDatabase so every test runs
// against a freshly-migrated in-memory SQLite schema (see phpunit.xml) — without
// this, DB-touching tests fail with "no such table" because migrations never run.

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
}
