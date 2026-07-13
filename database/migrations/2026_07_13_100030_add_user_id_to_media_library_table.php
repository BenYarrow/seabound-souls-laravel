<?php

// Ownership on media_library. NULL = house media (owned by the owner, invisible
// to riders). A rider's uploads carry their user_id and are only ever visible to
// them and to owners.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('media_library', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
