<?php

// Riders get structured first/last names (for bylines, sorting, initials later).
// `name` stays the canonical display column (kept in sync as "First Last" by a
// User saving hook) so auth/account displays keep working. Only riders are
// backfilled — the owner's `name` is the house brand ("Seabound Souls"), not a
// person, so its first/last stay null.

use App\Models\User;
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

        // Split existing riders' single name into first/last (first token, rest).
        DB::table('users')->where('role', User::ROLE_RIDER)->orderBy('id')
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
