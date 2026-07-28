# Reconcile Everything — Seabound Souls (Laravel) project data

**This is a data file** referenced by the global `reconcile-everything` skill at `~/.claude/skills/reconcile-everything/SKILL.md`. The global skill carries the procedure logic; this file supplies project-specific values.

**Note:** This is Ben's personal project, **not** an IFP repo — but it is deliberately held to IFP working standards (see repo `CLAUDE.md` → "Working standard").

Update this file when project conventions change. Don't put procedure logic here. All paths must be portable (repo-relative or formula-based).

## Paths

| Key | Value |
|---|---|
| `memory_dir` | `~/.claude/projects/-Users-benyarrow-sites-personal-claude-seabound-souls-laravel/memory/` |
| `situation_report` | `SITREP.md` |
| `todo` | `docs/TODO.md` |
| `history_docs` | `docs/history/` _(filenames: `YYYY-MM-DD-<slug>.md`)_ |
| `docs_index` | `docs/README.md` |
| `repo_level_claude_md` | `CLAUDE.md` |

## Stack / framework

| Key | Value |
|---|---|
| `language` | PHP 8.2+ · TypeScript / React 19 |
| `framework` | Laravel 12 + Inertia v2 + Filament 3.3 (+ Vite 7) |
| `route_list_cmd` | `php artisan route:list` |
| `source_ext` | `*.php`, `*.{ts,tsx}` |
| `test_runner` | `php artisan test` (PHPUnit 11) |
| `frontend_build_cmd` | `npm run build` _(requires Node 22+; shell default v14 fails — see CLAUDE.md)_ |
| `db_engine` | PostgreSQL. Local `seabound_souls_dev` on Homebrew `postgresql@16` (server); prod DB is **`main`** on Laravel Cloud / Neon (Postgres **17**). Client tools `postgresql@17` also installed locally for `db:pull-from-production`. Tests run on in-memory SQLite (`phpunit.xml`). |
| `deploy_platform` | Laravel Cloud (serverless Postgres) — **live** since 2026-07-09, `https://seabound-souls-production-ewycw6.laravel.cloud` (see SITREP roadmap #18/#19); custom domain still pending |

## Code directories — for stable-doc deep-audit (10a)

- `app/Http/Controllers/`
- `app/Models/`
- `app/Filament/`
- `routes/web.php`, `routes/api.php`
- `database/migrations/`
- `resources/js/` — Inertia React pages (`Pages/`) + components (`Components/`)

## Reconcile mode — folded (default)

Run the reconcile on the active feature branch *before* it merges, so reconcile docs ride in the same PR as the code. Diff window: `git merge-base origin/main HEAD`..HEAD. History doc named with the feature/PR slug. `reconciled` tag finalised post-merge.

## Dance procedure reference

- Global skill `~/.claude/skills/git-dance/` (no project-level `project.md` override yet).

## Notable historical incidents

**2026-07-05 — Node v14 default breaks Vite 7**

The shell's default Node (nvm v14.16.0) cannot run Vite 7 — `npm run dev` dies with `Cannot find module 'node:path'`. Dev and build must run under Node 22+ (`v22.22.3` installed). Sub-agents inherit the v14 default in their fresh shells and must `source ~/.nvm/nvm.sh && nvm use 22` first. No `.nvmrc` committed yet — adding one is a pending follow-up.

## Project quirks

- **Design reference is a sibling repo:** `../seabound-souls-sanity-next-js/` (the original Next.js 15 + Sanity build). Do NOT read it unless Ben explicitly instructs it in the moment — the port is documented in `CLAUDE.md`, which is sufficient for most work (the "stay in this directory" rule applies here).
- **Centralised media:** all images live once in `media_library` (Spatie Media Library), referenced by FK. Requires the `public/storage → storage/app/public` symlink.
