<?php

// Rename the existing "about-us" Page to "about" (prod holds this row). Idempotent
// and reversible. The nav link + a 301 from /about-us are handled in code/routes.

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Page::withTrashed()->where('slug', 'about-us')->update(['slug' => 'about']);
    }

    public function down(): void
    {
        Page::withTrashed()->where('slug', 'about')->update(['slug' => 'about-us']);
    }
};
