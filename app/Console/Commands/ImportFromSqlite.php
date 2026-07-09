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
