<?php

// Filament's database-notifications (the panel bell) filters with
// `where data->>'format' = 'filament'`. Postgres only permits the `->>` JSON
// operator on a json/jsonb column, but the stock Laravel notifications migration
// (2026_07_09_085707) created `data` as TEXT — so once ->databaseNotifications()
// is enabled the admin panel 500s with "operator does not exist: text ->> unknown".
// Convert the column to jsonb on Postgres. SQLite (the test connection) applies
// JSON paths to a text column natively, so this is a pgsql-only fix and a no-op
// elsewhere. Existing rows are valid JSON (Laravel always serialises notification
// data as JSON), so the USING cast is safe.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
        }
    }
};
