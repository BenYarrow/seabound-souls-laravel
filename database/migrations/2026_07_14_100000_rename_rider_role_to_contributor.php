<?php

// The contributor role shipped as 'rider' (sub-project 1) and was renamed to
// 'contributor' shortly after. Migrate existing rows and change the column
// default. Safe: production has no contributor rows yet, and the only local
// data is test accounts.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'rider')->update(['role' => 'contributor']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('contributor')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'contributor')->update(['role' => 'rider']);

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('rider')->change();
        });
    }
};
