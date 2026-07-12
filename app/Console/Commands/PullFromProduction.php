<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;

/**
 * Overwrites the local dev database with a fresh copy of production, so local
 * dev/debug runs against realistic content. Read-only on production (a
 * pg_dump), destructive only on the local database.
 *
 * Reads the production connection from the `pgsql_prod` config block
 * (PROD_DB_* env vars, local .env only). Restores into the local `pgsql`
 * connection. Media files (R2 object storage) are NOT pulled — see the design
 * doc; local MEDIA_DISK stays on the local disk so test uploads keep working.
 *
 * Guards run before any external work: local env only, production credentials
 * present, destination host must be local, and the local pg_dump must be new
 * enough for the production server. See docs/superpowers/specs.
 */
class PullFromProduction extends Command
{
    protected $signature = 'db:pull-from-production {--force : Skip the confirmation prompt}';

    protected $description = 'Overwrite the local database with a fresh copy of production (read-only on prod, destructive on local)';

    /** Tables to report row counts for after the restore, as a sanity check. */
    private const REPORT_TABLES = ['spot_guides', 'blogs', 'pages', 'media_library'];

    /**
     * Extract the major version from a Postgres version string. Handles both
     * the pg_dump banner ("pg_dump (PostgreSQL) 17.10 (Homebrew)") and a bare
     * server version ("17.10"). Returns null when no version number is present.
     *
     * @param  string  $version  a version string from pg_dump or the server
     * @return int|null the major version, or null if unparseable
     */
    public static function majorVersion(string $version): ?int
    {
        if (preg_match('/(\d+)\.\d+/', $version, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)/', $version, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Whether a host refers to the local machine. Used to guarantee the restore
     * target is local — the command must never write to a remote database.
     *
     * @param  string|null  $host  the connection host
     */
    public static function isLocalHost(?string $host): bool
    {
        return in_array($host, ['127.0.0.1', 'localhost', '::1'], true);
    }

    /**
     * Run the pull. Returns an exit code (SUCCESS / FAILURE).
     */
    public function handle(): int
    {
        // Guard 1: local environment only — never in production or CI.
        if (! $this->getLaravel()->environment('local')) {
            $this->error('db:pull-from-production only runs in the local environment (APP_ENV=local).');

            return self::FAILURE;
        }

        // Guard 2: production credentials must be configured.
        $prod = config('database.connections.pgsql_prod');
        foreach (['host', 'database', 'username', 'password'] as $key) {
            if (empty($prod[$key])) {
                $this->error("Production DB not configured — set the PROD_DB_* vars in your local .env (missing PROD_DB_".strtoupper($key).').');

                return self::FAILURE;
            }
        }

        // Guard 3: the restore target must be local. This command only ever
        // overwrites the local database.
        $local = config('database.connections.pgsql');
        if (! self::isLocalHost($local['host'] ?? null)) {
            $this->error("Refusing to run: the destination host '".($local['host'] ?? 'null')."' is not local. This command only overwrites a local database.");

            return self::FAILURE;
        }

        // Guard 4: the local pg_dump must be at least as new as the server.
        $pgDump = $this->pgDumpBinary();
        $pgRestore = $this->pgRestoreBinary();

        $clientVersion = Process::run([$pgDump, '--version']);
        $clientMajor = self::majorVersion($clientVersion->output());

        try {
            $serverVersion = (string) DB::connection('pgsql_prod')->selectOne('show server_version')->server_version;
        } catch (\Throwable $exception) {
            $this->error('Could not connect to production: '.$exception->getMessage());

            return self::FAILURE;
        }
        $serverMajor = self::majorVersion($serverVersion);

        if ($clientMajor === null || $serverMajor === null) {
            $this->error("Could not determine Postgres versions (client: '{$clientVersion->output()}', server: '{$serverVersion}').");

            return self::FAILURE;
        }

        if ($clientMajor < $serverMajor) {
            $this->error("pg_dump {$clientMajor} is too old for the production server (Postgres {$serverMajor}). Install postgresql@{$serverMajor} (`brew install postgresql@{$serverMajor}`) or point PG_DUMP_PATH at a new-enough binary.");

            return self::FAILURE;
        }

        // Confirm — this erases the local database.
        $this->warn("Source:      {$prod['database']} @ {$prod['host']} (production, read-only)");
        $this->warn("Destination: {$local['database']} @ {$local['host']} (LOCAL — will be ERASED)");
        if (! $this->option('force') && ! $this->confirm('Overwrite the local database with production?')) {
            $this->info('Aborted — nothing changed.');

            return self::SUCCESS;
        }

        $dumpFile = storage_path('app/prod-pull-'.now()->format('Ymd-His').'.dump');

        // Dump production (read-only). Custom format so pg_restore can --clean.
        $this->info('Dumping production…');
        $dump = Process::timeout(600)->env(['PGPASSWORD' => $prod['password'], 'PGSSLMODE' => $prod['sslmode'] ?? 'require'])
            ->run([
                $pgDump, '-Fc',
                '-h', $prod['host'], '-p', (string) $prod['port'],
                '-U', $prod['username'], '-d', $prod['database'],
                '-f', $dumpFile,
            ]);
        if (! $dump->successful()) {
            $this->error('pg_dump failed: '.$dump->errorOutput());

            return self::FAILURE;
        }

        // Restore into local, dropping existing objects first.
        $this->info('Restoring into local…');
        $restore = Process::timeout(600)->env(['PGPASSWORD' => (string) ($local['password'] ?? '')])
            ->run([
                $pgRestore, '--clean', '--if-exists', '--no-owner', '--no-privileges',
                '-h', $local['host'], '-p', (string) $local['port'],
                '-U', $local['username'], '-d', $local['database'],
                $dumpFile,
            ]);

        // pg_restore exits non-zero for benign "does not exist, skipping" notices
        // under --clean on a fresh DB; surface stderr but only fail if the DB
        // ended up without our core tables.
        @unlink($dumpFile);

        // Production stores media on the R2 (s3) disk. Locally there is no
        // bucket configured, so leaving the pulled `media` rows on 's3' makes
        // every image URL throw (GetObject requires non-empty Bucket → 500).
        // Repoint every media row at the local media-library disk. The files
        // themselves are not synced (DB-only; see the --with-media follow-up),
        // so images may 404 — but pages render.
        $localMediaDisk = config('media-library.disk_name');
        try {
            $remapped = DB::connection('pgsql')->table('media')
                ->update(['disk' => $localMediaDisk, 'conversions_disk' => $localMediaDisk]);
            if ($remapped > 0) {
                $this->info("Repointed {$remapped} media row(s) to the local '{$localMediaDisk}' disk (files not synced — images may not render locally).");
            }
        } catch (\Throwable $exception) {
            // A schema without a `media` table is unexpected but non-fatal here.
            $this->warn('Could not remap media disk (non-fatal): '.$exception->getMessage());
        }

        $this->newLine();
        $this->info('Restored. Local row counts:');
        foreach (self::REPORT_TABLES as $table) {
            try {
                $count = DB::connection('pgsql')->table($table)->count();
                $this->line("  {$table}: {$count}");
            } catch (\Throwable $exception) {
                $this->error("  {$table}: could not read ({$exception->getMessage()})");

                return self::FAILURE;
            }
        }

        if (! $restore->successful()) {
            $this->warn('pg_restore reported warnings (often benign --clean notices on a fresh DB):');
            $this->line($restore->errorOutput());
        }

        return self::SUCCESS;
    }

    /**
     * The pg_dump binary to use: PG_DUMP_PATH if set, else the Homebrew
     * postgresql@17 binary when present, else whatever is on PATH. The version
     * guard reports a clear error if the chosen binary is too old.
     */
    private function pgDumpBinary(): string
    {
        return env('PG_DUMP_PATH')
            ?: (is_executable('/opt/homebrew/opt/postgresql@17/bin/pg_dump')
                ? '/opt/homebrew/opt/postgresql@17/bin/pg_dump'
                : 'pg_dump');
    }

    /**
     * The pg_restore binary to use (mirrors {@see pgDumpBinary}).
     */
    private function pgRestoreBinary(): string
    {
        return env('PG_RESTORE_PATH')
            ?: (is_executable('/opt/homebrew/opt/postgresql@17/bin/pg_restore')
                ? '/opt/homebrew/opt/postgresql@17/bin/pg_restore'
                : 'pg_restore');
    }
}
