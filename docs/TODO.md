# TODO — Seabound Souls (Laravel)

Forward-looking backlog. Completed work is recorded in `docs/history/` and the `SITREP.md` roadmap, not here.

## Testing sweep (fan out the blog template)
- [ ] SpotGuide — controller + model + relationships (recommendations, windsurfing locations, weather records) + comments
- [ ] Destinations — controller + weather-data prop shape + comments
- [ ] Search — controller + Scout behaviour + comments
- [ ] Contact — index + store (validation, mocked mail + reCAPTCHA) + comments
- [ ] Pages — catch-all controller + comments
- [ ] Homepage — controller + featured/recent props + comments
- [ ] Helpers/logic units — weather-data transforms, `LiveWeatherController` caching
- [ ] Filament resources — smoke tests (lowest priority)

## Tooling
- [ ] Add `.nvmrc` pinning Node 22 so the correct version is auto-selected
- [ ] Set up husky + lint-staged + eslint-plugin-jsdoc pre-commit enforcement (JSDoc-on-every-function rule)

## Frontend
- [ ] Dark-mode token layer (CSS vars on `:root` / `html.dark`), no-flash theme switch, CI colour-guard test
- [ ] Full responsive audit across mobile / tablet / desktop breakpoints
