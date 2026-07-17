<?php

// Tests the about-us → about slug rename: /about serves the page, /about-us
// permanently redirects to it (so old links survive).

namespace Tests\Feature;

use App\Models\Page;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutRenameTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_serves_the_page_and_about_us_redirects(): void
    {
        Page::factory()->create(['slug' => 'about', 'title' => 'About', 'is_published' => true]);

        $this->get('/about')->assertOk();
        $this->get('/about-us')->assertRedirect('/about')->assertStatus(301);
    }
}
