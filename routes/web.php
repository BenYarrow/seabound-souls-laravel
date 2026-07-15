<?php

use App\Http\Controllers\Admin\MediaFocalController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Contributor\SetPasswordController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SpotGuideController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomepageController::class, 'index'])->name('home');
Route::get('/destinations', [DestinationController::class, 'index'])->name('destinations.index');
Route::get('/destinations/{slug}', [SpotGuideController::class, 'show'])->name('spot-guides.show');
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
// Tag routes are declared BEFORE /blog/{slug} so the literal "/blog/tags" hub
// isn't captured as a blog post with slug "tags".
Route::get('/blog/tags', [TagController::class, 'index'])->name('blog.tags.index');
Route::get('/blog/tags/{slug}', [TagController::class, 'show'])->name('blog.tags.show');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::get('/contact', [ContactController::class, 'index'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

// Admin: set focal point for a media library item (called by MediaPicker Alpine click handler)
Route::post('/admin/media/{media}/focal', [MediaFocalController::class, 'store'])
    ->middleware(['web', 'auth'])
    ->name('admin.media.focal');

// Invited-contributor password setup. Both routes are signed (temporary), so the link
// the owner sends is the only way in; no auth session is required to use them.
Route::get('/contributor/set-password/{user}', [SetPasswordController::class, 'show'])
    ->middleware('signed')->name('contributor.password.setup');
Route::post('/contributor/set-password/{user}', [SetPasswordController::class, 'store'])
    ->middleware('signed')->name('contributor.password.store');

// SEO: dynamic XML sitemap (generated on request, cached — see SitemapController).
// Declared before the catch-all so it isn't swallowed by it. robots.txt is a
// static file (public/robots.txt) — web servers serve /robots.txt directly.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

// Catch-all for generic pages (must be last, exclude admin paths)
Route::get('/{slug}', [PageController::class, 'show'])->where('slug', '^(?!admin).*$')->name('pages.show');
