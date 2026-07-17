# Frontend engine E2E tests

Headless-Chromium regression suite for the popups frontend engine
(`src/Modules/Popups/assets/scripts/engine.js`). The harness page mirrors the
real runtime contract: its markup matches `PopupRenderer::build_markup()` and
its `window.elemacyPopups` config matches `PopupConfigResource`. If either
contract changes, update `popups.html` in the same commit.

```bash
cd tests/e2e
npm install
ELEMACY_CHROMIUM=/path/to/chrome npm test   # or let playwright-core resolve it
```

Never shipped: `tests/` is excluded by `.distignore`.

The pro plugin's `tests/e2e` extends this with the pro triggers/rules/analytics
suite (it loads this repo's engine.js from the sibling checkout).
