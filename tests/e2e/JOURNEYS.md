# Native user journeys

Development-only Playwright journeys live in `journeys/`, separately from the existing focused `run-*.mjs` regressions. They click actual UI controls and save through real WordPress/Elementor requests. Prerequisite sessions and cleanup use native guarded fixtures. No plugin REST or form submission response is replaced.

This suite is under implementation, not a complete release gate. Module toggles, library management/content-copy, classic Footer color/save/visitor, and default modal popup authoring slices pass on the production Free+Pro site. The expanded Header-only test detects a real Hello compatibility bug: its native footer disappears. That assertion stays enabled; the user chose to continue other tests before fixing product code. Other planned families, atomic styling, branches/profiles and Pro journeys remain pending. Central capture currently applies to the new Free journeys; adapters for retained scripts/Pro are still pending.

## Setup

From the Free plugin root, provision an owned site using the existing integration guide. Export the exact manifest path printed by setup:

```bash
export ELEMACY_TEST_ENV=/tmp/elemacy-integration-<token>/environment.json
npm ci --prefix tests/e2e
```

Use the browser build matching the locked Playwright version. The default cached headless Chromium is supported; `ELEMACY_CHROMIUM` can specify a tested executable. Run native suites sequentially against one environment. No manifest falls back to the development site. Playwright starts/stops its guarded PHP server automatically.

### Managed production site

For local nginx/PHP-FPM browser tests, create a separate disposable site with wp-site-manager named `elemacy-release.test` (or `elemacy-release-<name>.test`). Install the verified production ZIPs, pinned Elementor and a real theme there. The PHP worker must be able to write uploads; use the site's intended direct filesystem configuration rather than an unconfigured FTP fallback.

Register only this disposable site after installation:

```bash
python3 tests/integration/register-managed-browser.py \
  --domain elemacy-release.test \
  --free-zip /absolute/path/elemacy-1.0.0.zip \
  --pro-zip /absolute/path/elemacy-pro-1.0.0.zip \
  --confirm-disposable-site
export ELEMACY_TEST_ENV=/tmp/elemacy-browser-<printed-token>/environment.json
```

Registration compares every installed release file with the supplied ZIPs, records hashes and a site ownership token, and uses the existing external server. It cannot register `elemacy.test`. Reuse the printed manifest for subsequent runs; changing configuration or installed package files invalidates it. Never point the isolated PHP server/router at a managed site. The harness uses temporary per-case authors and requires a matching site/database/token before cleanup; it never deletes the site.

## Run and choose media

Explicit output directories must be new; an existing directory is rejected to protect unrelated files. Defaults create a fresh private directory automatically.

```bash
# Discover cases without a running site.
npm run test:journeys --prefix tests/e2e -- --list

# One family, screenshots at named checkpoints, video, and trace.
npm run test:journeys --prefix tests/e2e -- --grep @modules --screenshots=all --video=all --trace=all

# One stable case ID; use a unique output directory for each run.
npm run test:journeys --prefix tests/e2e -- --grep 'elemacy:journey:module:widgets' --output=/tmp/elemacy-widget-module-run

# No optional media; assertions and failure status are unchanged.
npm run test:journeys --prefix tests/e2e -- --grep @modules --screenshots=off --video=off --trace=off

# Includes the header authoring slice; this is not full release readiness.
npm run test:smoke --prefix tests/e2e
```

Screenshot/video/trace choices are independent: `off`, `failure`, or `all`. Defaults are failure screenshots and traces, with video off. Reports record the effective choices. Invalid values fail before test execution. Choose media before the run; videos cannot be recovered from a run where recording was off.

Each unique run directory contains `html/index.html`, JSON and JUnit results, server logs, and case-specific artifacts. All-mode screenshots use named checkpoints plus the final screenshot. Playwright closes contexts before retaining videos. Known native session cookies/nonces are removed from retained trace/text artifacts after execution; run reports still need protected handling for any additional secrets introduced by future live-service cases. Sessions are revoked and owned documents/users are deleted during teardown. Interrupted runs may require fixture cleanup using their guarded manifests.

Google Fonts CSS and Gravatar images are deterministic decorative fixtures to avoid remote-service stalls. Native application traffic stays real. Remote font/avatar delivery is not established by this profile.

Set `ELEMACY_TEST_DECORATIVE_SERVICES=live` to compare with actual font/avatar requests and no decorative interception. Reports record this choice; native application requests and assertions are identical.

The feature inventory and generated roadmap are under `tests/coverage/`. Inventory validation does not mean the full test suite or release artifact passed.

## Browser rendering and diagnostics

Both plugins pin Playwright 1.62.0 and use its matching Chromium 151.0.7922.34. The older 1.61.1 launch defaults disabled Chromium's `RenderDocument`; a controlled comparison showed that this froze Elementor's loader fade-out and screenshots after navigation. Upgrading fixes rendering without injected loader changes, browser-feature overrides or reduced-motion workarounds. The editor URL, visible readiness, native drag/drop, save/reopen and signed-out visitor assertions remain enabled.

Diagnostics retain every event. A native `AbortError` with exactly `Transition was skipped` and an empty stack is reported separately only within two seconds of a same-origin Elemacy-admin-to-Elementor navigation. Chromium's outgoing cross-document transition may reject its ready promise when its document is hidden ([Chrome documentation](https://developer.chrome.com/docs/web-platform/view-transitions/cross-document)). This cancellation does not skip any functional assertion. Different errors, stacks, routes or timing still fail. `node --test shared/diagnostics.test.mjs` checks those boundaries; a temporary native browser failure injection also verified nonzero failure with optional media disabled. Reports include the browser version and cancellation evidence.
