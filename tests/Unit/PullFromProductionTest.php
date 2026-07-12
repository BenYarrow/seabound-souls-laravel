<?php

// Unit tests for the pure helpers on App\Console\Commands\PullFromProduction:
// version-string parsing (used for the pg_dump ≥ server compatibility guard)
// and the local-host check (used to guarantee the command only ever overwrites
// a local database). These are network-free and don't shell out.

namespace Tests\Unit;

use App\Console\Commands\PullFromProduction;
use Tests\TestCase;

class PullFromProductionTest extends TestCase
{
    /** @dataProvider versionStrings */
    public function test_major_version_parses_the_leading_major(string $input, ?int $expected): void
    {
        $this->assertSame($expected, PullFromProduction::majorVersion($input));
    }

    public static function versionStrings(): array
    {
        return [
            'pg_dump banner'   => ['pg_dump (PostgreSQL) 17.10 (Homebrew)', 17],
            'older pg_dump'    => ['pg_dump (PostgreSQL) 16.13 (Homebrew)', 16],
            'bare server ver'  => ['17.10', 17],
            'major only'       => ['18', 18],
            'no digits'        => ['unknown', null],
        ];
    }

    /** @dataProvider hosts */
    public function test_is_local_host(?string $host, bool $expected): void
    {
        $this->assertSame($expected, PullFromProduction::isLocalHost($host));
    }

    public static function hosts(): array
    {
        return [
            'loopback ipv4' => ['127.0.0.1', true],
            'localhost'     => ['localhost', true],
            'loopback ipv6' => ['::1', true],
            'prod host'     => ['ep-small-glitter-ab1ls2du.aws-eu-west-2.pg.laravel.cloud', false],
            'empty'         => ['', false],
            'null'          => [null, false],
        ];
    }
}
