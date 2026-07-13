<?php

// Adds the two-tier role column to users. Default 'rider' because the only way
// new accounts are created going forward is the owner inviting riders; the single
// pre-existing owner account is promoted to 'owner' below.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('rider')->after('email');
        });

        // Promote the existing owner account (identified by the config email).
        DB::table('users')->where('email', config('admin.email'))->update(['role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
