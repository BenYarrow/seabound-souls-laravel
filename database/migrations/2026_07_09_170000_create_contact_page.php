<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

// Seed a Page record for the contact page so its SEO (title/description/
// keywords/OG image) is editable in the Pages admin like the other system
// pages. The page's content is not rendered — ContactController owns the view;
// this row is an SEO holder. Runs on deploy so production gets it too.
return new class extends Migration
{
    public function up(): void
    {
        Page::firstOrCreate(
            ['slug' => 'contact'],
            ['title' => 'Contact', 'template' => 'standard', 'is_published' => true],
        );
    }

    public function down(): void
    {
        Page::where('slug', 'contact')->where('template', 'standard')->delete();
    }
};
