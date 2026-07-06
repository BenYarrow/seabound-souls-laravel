# TODO — Seabound Souls (Laravel)

Forward-looking backlog. Completed work is recorded in `docs/history/` and the `SITREP.md` roadmap, not here.

## Testing sweep
Public controllers all covered. Remaining:
- [ ] Helpers/logic units — weather-data transforms, `LiveWeatherController` caching (external HTTP + response cache)
- [ ] `Api\WeatherDataController` (index + show)
- [ ] Filament resources — smoke tests (lowest priority)

## Tooling
- [ ] CI pipeline (GitHub Actions) running `php artisan test` on every PR — would have caught the Vite-dependent-suite fragility immediately
- [ ] Add `.nvmrc` pinning Node 22 so the correct version is auto-selected
- [ ] Set up husky + lint-staged + eslint-plugin-jsdoc pre-commit enforcement (JSDoc-on-every-function rule)

## Frontend
- [ ] Dark-mode token layer (CSS vars on `:root` / `html.dark`), no-flash theme switch, CI colour-guard test
- [ ] Full responsive audit across mobile / tablet / desktop breakpoints
