---
title: Claude onboarding, test harness fix, and blog test/comment slice
tags: [onboarding, testing, tooling, blog]
status: stable
completed: 2026-07-05
commits: [801e72a, e48ed25, d17830e, 0eb7d4b]
pr: 1
---

# Onboarding + test harness + blog slice

First substantive Claude-driven session on the repo. Established the working scaffolding and the first real test coverage.

## What shipped

### Onboarding scaffolding (commit 801e72a)
- **`CLAUDE.md` "Working standard"** section added: scope (stay-in-directory — the sibling Next.js reference is off-limits unless explicitly asked), session-start notes (Node quirk, dev servers, sub-agent nvm), git workflow (branch+PR, keep `main`, reconcile-fold), TDD, code clarity + JSDoc/husky, dark mode + responsive, migration discipline.
- **`.claude/launch.json`** — Laravel dev-server preview config.
- **`.claude/skills/reconcile-everything/project.md`** — project data for this skill.

### Test harness fix + blog slice (commits d17830e, via PR #1)
- **Harness:** `TestCase` now uses `RefreshDatabase` so migrations actually run against `:memory:` SQLite. Previously the one meaningful test was red (`no such table: pages`) because migrations never ran. `phpunit.xml` gained `SCOUT_DRIVER=null` to disable Scout syncing in tests. Stock example tests removed.
- **Factories:** `BlogFactory` + `PageFactory` (models given `HasFactory`); default to the published happy-path with named states (`unpublished()`, `slug()`).
- **`BlogControllerTest`** — 7 feature tests, 60 assertions: index render, published-only filtering, 12-per-page pagination, masthead lookup, show render, 404 on draft, 404 on unknown slug.
- **Comments pass:** module headers + PHPDoc + why-comments on `BlogController` and `Blog`, per the working standard. This slice is the **template** for the wider sweep.

## Findings worth keeping
- **Node v14 default breaks Vite 7.** The shell default is nvm v14.16.0; Vite 7 needs Node 22+. Dev/build and any sub-agent must select v22 first. `.nvmrc` not yet committed.
- **The test suite was non-functional**, not just thin — no `RefreshDatabase` meant zero DB-touching tests could pass. Fixing that was the prerequisite for all future test work.

## Test plan
`php artisan test` → 7 passed, 60 assertions. `/blog` returns 200 in the browser.

## Follow-ups
See `docs/TODO.md`. Highest-value next: fan out the blog test/comment pattern across the remaining public controllers (SpotGuide first), then models, then Filament. Separate tracks: `.nvmrc`, husky/eslint-jsdoc enforcement, dark-mode token layer + responsive audit.
