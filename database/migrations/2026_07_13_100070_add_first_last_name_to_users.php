<?php

// Riders get structured first/last names (for bylines, sorting, initials later).
// `name` stays the canonical display column (kept in sync as "First Last" by a
// User saving hook) so auth/account displays keep working. Only riders are
// backfilled — the owner's `name` is the house brand ("Seabound Souls"), not a
// person, so its first/last stay null.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
        });

        // Split existing contributors' single name into first/last (first token, rest).
        // Literal 'rider' here on purpose: that was the role value at this point in
        // history — a later migration renames it to 'contributor'. Using the constant
        // would break this migration once the constant is renamed.
        DB::table('users')->where('role', 'rider')->orderBy('id')
            ->each(function ($user) {
                $parts = preg_split('/\s+/', trim((string) $user->name), 2);
                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $parts[0] ?? null,
                    'last_name' => $parts[1] ?? null,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
        });
    }
};
