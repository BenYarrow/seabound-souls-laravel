---
title: Dynamic cached XML sitemap + static robots.txt
tags: [seo, sitemap, robots, laravel-cloud]
status: stable
completed: 2026-07-14
commits: [ffca339]
pr: 34
---

# XML sitemap + robots.txt

## What shipped

A crawlable `/sitemap.xml` (which was 404ing on prod) plus a `robots.txt` pointing at it.

- **`App\Support\SitemapBuilder`** — single source of truth for the URL set: static pages (`/`, `/destinations`, `/blog`, `/contact`) + every published `SpotGuide`, `Blog`, and generic `Page` (home Page excluded — `/` already listed), each with `lastmod`/priority/changefreq via `spatie/laravel-sitemap`.
- **`SitemapController@index`** — returns the built XML with `Content-Type: application/xml`, cached an hour (`Cache::remember('sitemap.xml', …)`). Route declared before the catch-all `/{slug}`.
- **`public/robots.txt`** — static file, `Disallow:` (allow all) + a `Sitemap:` line.

## Findings worth keeping

- **Why dynamic, not a generated static file:** Laravel Cloud's filesystem is ephemeral/immutable, so a written `public/sitemap.xml` isn't reliably served. Generating on request (cached) keeps it working everywhere and always fresh; the URLs inside adapt to the serving host, so it's correct on local / staging / prod / the eventual custom domain with no per-env step.
- **robots.txt is a static file, not a route.** Herd/Valet's nginx special-cases `/robots.txt` and `/favicon.ico` and serves them (or 404s) *before* the request reaches Laravel — a route named `robots.txt` would never fire locally. Verified: an arbitrary `/_probe.txt` served 200 while `/robots.txt` 404'd under Herd even when a route existed. Static file it is; it serves 200 on Cloud.
- **Cost on Laravel Cloud:** negligible — no cron, just a cached route hit occasionally by crawlers.

## Test plan

`tests/Feature/SitemapTest.php` — asserts `/sitemap.xml` is XML, 200s, lists published guides only (drafts excluded). Tests run with `CACHE_STORE=array`; `Cache::flush()` in `setUp` so each test sees fresh output.

## Follow-ups

- On custom-domain launch, update the one `Sitemap:` line in `public/robots.txt` to the real domain and submit the sitemap in Google Search Console (tracked in `docs/TODO.md`). The sitemap route itself needs no change.
- (Superseded by the blog-tags work the same day: tag pages + the `/blog/tags` hub were added to `SitemapBuilder` — see [2026-07-14-blog-tags](2026-07-14-blog-tags.md).)
- (Superseded 2026-08-06: `SitemapBuilder` also now emits photographer profile pages — `Photographer::withPublicPage()` entries at `/photographers/{slug}` — see [2026-08-06-photographer-attribution](2026-08-06-photographer-attribution.md).)
