---
title: Search test + comment slice
tags: [testing, search, scout]
status: stable
completed: 2026-07-05
commits: [b4b74fc]
pr: 5
---

# Search test + comment slice

Fourth slice of the up-to-speed sweep. Test coverage + comment pass for the `/search` page.

## What shipped
- **`SearchControllerTest`** (4): renders with no query, short-query (<2 chars) short-circuit, finds published spot guides (excludes drafts), finds published blogs.
- Comments: module header + PHPDoc on `SearchController`.

## Findings worth keeping
- **Scout testing pattern established.** The suite runs `SCOUT_DRIVER=null` (from PR #1) so most tests don't touch a search engine. The Search tests are the exception: they set `config(['scout.driver' => 'collection'])` in `setUp()`, which makes Scout match **in-process** against each model's `toSearchableArray` — real matching, no external service. This is the template for any future search-touching test.
- The controller **short-circuits queries under two characters** to empty results, and constrains each Scout query to published rows via the `->query()` callback, so drafts never surface.

## Test plan
`php artisan test` → 29 passed, 224 assertions. `/search?q=…` renders (200).

## Follow-ups
See `docs/TODO.md`. Next slice: Contact (index + store, with validation + mocked mail/reCAPTCHA), then Pages, Homepage.
