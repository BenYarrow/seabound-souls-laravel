<?php

// Records the author of each spot guide. Nullable + nullOnDelete so removing a
// user never deletes their guides (the house keeps published content). Existing
// guides are backfilled to the owner account.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        $ownerId = DB::table('users')->where('email', config('admin.email'))->value('id');
        if ($ownerId !== null) {
            DB::table('spot_guides')->whereNull('user_id')->update(['user_id' => $ownerId]);
        }
    }

    public function down(): void
    {
        Schema::table('spot_guides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
