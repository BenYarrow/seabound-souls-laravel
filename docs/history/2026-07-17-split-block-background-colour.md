---
title: Background-colour option on the Split Image & Text block
tags: [content-builder, filament, frontend]
status: stable
completed: 2026-07-17
commits: [cc5a7be]
pr: 37
---

# Split block background colour

## What shipped

The `split_image_text` content block gained a **Background Colour** option (the shared `ContentBuilderBlocks::backgroundColourSelect()`), so consecutive split blocks on a page can alternate backgrounds instead of all being cream.

- Block schema: `static::backgroundColourSelect()->default('bg-cream')` — cream default preserves the block's established look.
- Added **Cream** (`bg-cream`) to the shared select's options — it was already in the Tailwind `safelist` but wasn't offered in the dropdown (so it's now selectable for every block that uses the select).
- `backgroundColour` is threaded through `ContentBuilder` → `SplitImageText`, replacing the previously hardcoded `bg-cream`.
- `SplitImageText` inverts its prose text on dark backgrounds (`bg-secondary`/`bg-primary`/`bg-primary-darker`), mirroring `RichText`, so text stays readable if Primary/Secondary is chosen.

## Findings worth keeping

- Backward compatibility: existing split blocks store no `backgroundColour`, so `ContentBuilder` passes `undefined` and the component's `backgroundColour = 'bg-cream'` default keeps them cream — appearance unchanged.
- The shared `backgroundColourSelect()` is reused by `rich_text`, `single_image`, `image_pair`; adding the `bg-cream` option benefits all of them (all these classes were already safelisted).

## Test plan

`npm run build` clean; full suite **246 passing** (no new tests — this mirrors existing, untested presentational blocks; the JS suite only covers pure helpers). Visual behaviour (the chosen colour rendering + dark-bg text inversion) is an owner eyeball, per the session's browser-tooling limitation.

## Follow-ups

- None. If a future block needs a colour not in the select, extend `backgroundColourSelect()` (and confirm the class is in `tailwind.config.ts` `safelist`).
