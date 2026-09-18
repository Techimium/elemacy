# Admin extension compatibility

The first-release candidate supports Free 1.0.0 with Pro 1.0.0, using bridge and declaration contract 1.0.0. This is the pair under development in these checkouts; generated ZIP verification remains the release packaging gate. No historical or future plugin pair is claimed as tested.

Free owns React, ReactDOM, shared UI components, registries, routes, toast, and base styles. Its bridge publishes `version` and `capabilities`, then emits `elemacy:admin-ready`. Pro's loader accepts contract major 1 with required capabilities and runtime fields, either already ready or delivered within five seconds. Unsupported/missing contracts show a diagnostic while leaving Free usable. Protocol acceptance of other major-1 versions is not release compatibility evidence for untested plugin builds.

Pro uses its own dependency lockfile and `contracts/elemacy-admin-contract-1.0.0.tgz`; Free's `npm run build:contract` produces the narrow declarations. See Pro's contract README for updating the pinned artifact. Pro's prefixed utilities are additive to Free's stylesheet, and its production build rejects bundled React/ReactDOM code.

Run both admin builds, Free lint, and the guarded `tests/e2e/run-admin-contract.mjs` suite. The browser suite includes normal delivery, a 1.5-second delay of either bundle, repeated readiness/loader execution, an injected unsupported-major diagnostic, library/popups/condition editing, license form validation, and the active analytics fill. The unsupported-major case is an injected protocol fixture, not another released plugin pairing. Saved screenshots support visual inspection; they are not historical screenshot-diff baselines.
