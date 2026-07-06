---
title: Filament custom theme + MediaPicker layout fix
tags: [filament, admin, theme, media-picker, ui]
status: stable
completed: 2026-07-06
commits: [bd89c9b]
pr: 10
---

# Filament custom theme + MediaPicker layout fix

Ben reported the admin's "Masthead & Thumbnail" section looked cramped — the preview image rendered huge with tiny alt text — and the media-library slide-over didn't grid.

## Root cause
The custom `MediaPicker` Blade views (`resources/views/filament/forms/components/media-picker.blade.php` and `resources/views/livewire/media-picker-browser.blade.php`) use Tailwind utilities (`w-40`, `aspect-video`, `grid-cols-6`, …). But **Filament compiles its own CSS and only emits the utilities it uses** — with no custom Filament theme scanning our view files, those classes were never generated, so the preview image had no size constraint (rendered at natural/full width) and the browser grid collapsed. Same cause for both complaints.

## What shipped
- **Custom Filament theme** via `php artisan make:filament-theme admin`, registered with `->viteTheme('resources/css/filament/admin/theme.css')` and added to the Vite `input`. The theme's Tailwind `content` was extended to scan `resources/views/livewire/**` (where the media browser lives) so its grid/aspect classes compile.
- **Redesigned the single-select MediaPicker preview** as a compact 16:9 card (constrained thumbnail + readable name + clear remove button, dark-mode aware) instead of the cramped flex row.
- `make:filament-theme` also moved build tooling (tailwindcss/postcss/autoprefixer/typography) into `devDependencies` and added `@tailwindcss/forms` + `postcss-nesting`.

## Findings worth keeping
- **Custom Tailwind classes in custom Filament views need a custom Filament theme** — otherwise they silently don't compile. Any future custom Filament view must use classes covered by the theme's `content` globs (now includes `app/Filament/**`, `resources/views/filament/**`, `resources/views/livewire/**`, `vendor/filament/**`).
- The admin's **dark mode is Filament's own** (enabled by default, follows the OS preference) — separate from the public site, which still has no dark mode (pending TODO).

## Test plan
`php artisan test` → 54 passed, 341 assertions (config-only change, no regressions). `/admin/login` returns 200 and references the compiled `theme-*.css`. Compiled theme CSS confirmed to contain the previously-missing classes (`aspect-video`, `w-64`, `grid-cols-6`, `aspect-square`). Visual confirmation of the field preview + browser grid is human-verified in the logged-in admin.

## Follow-ups
None specific. When the public-site dark-mode token layer is built, the Filament theme is unaffected (separate).
