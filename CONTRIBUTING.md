# Contributing to Elemacy

Elemacy is a free WordPress plugin that extends Elementor. The companion paid
add-on, **Elemacy Pro**, is a separate plugin that hooks into the seams documented
below. See `docs/STORAGE.md`, `docs/ADMIN-COMPATIBILITY.md`, and `docs/RELEASE-VALIDATION.md` for storage, compatibility, and recorded evidence.

## Prerequisites

- A local WordPress install with **Elementor** active.
- PHP 7.4+ and Composer.
- Node 20.19+ (or 22.12+) and npm.

## Setup

```bash
# From the plugin root:
composer install                 # PHP dev tooling (WPCS)

# From src-react/:
npm ci
```

For a live admin UI with hot reload, enable dev mode by adding this to
`wp-config.php` **before** the plugin loads, then start Vite:

```php
define('ELEMACY_ENV', 'dev');    // production is the default; opt in to HMR
```

```bash
# From src-react/:
npm run dev                      # Vite dev server on http://localhost:5173
```

Without `ELEMACY_ENV=dev`, the admin app loads the built bundle from
`assets/admin/`.

## Commands

| Where | Command | Purpose |
| --- | --- | --- |
| plugin root | `vendor/bin/phpcs` | Lint PHP against `phpcs.xml`. Target **0 errors**. |
| plugin root | `vendor/bin/phpcbf` | Auto-fix WPCS violations (`--tab-width=4`). |
| `src-react/` | `npm run dev` | Vite dev server (HMR). |
| `src-react/` | `npm run build` | `tsc -b && vite build` → `../assets/admin/`. |
| `src-react/` | `npm run lint` | ESLint over the React source. |
| `src-react/` | `npm run build:zip` | Produce the distributable production zip. |

Run `php tests/php/run.php` and `php tests/architecture/dependency-boundaries.php` from the plugin root. Native WordPress/database tests use `php tests/integration/run.php` with a guarded `ELEMACY_TEST_ENV`; browser commands and setup are in `tests/integration/README.md`. Keep fixtures outside release artifacts.

## Architecture in one paragraph

`elemacy.php` boots `Core\Elemacy`, which wires everything on `plugins_loaded`.
Features are **modules** (`Core\Module`, registered in `Config/modules.php`, toggled
by the `elemacy_active_modules` option). Every template-like item — Theme Builder
templates *and* Popups — lives on **one** CPT, `elemacy_library`, discriminated by
the `_elemacy_template_type` meta and resolved through `TemplateLibrary\TemplateResolver`
against the shared **conditions engine** (`Conditions/`). REST goes through the
`Core\Route` facade (namespace `elemacy`); frontend AJAX through `Core\AjaxRouter`. Every route declares `public_access()` or `require_capability()`, and every AJAX registration declares its user/capability policy. Public nonces never replace saved-document or host authorization.
The admin is a single React SPA (`src-react/`) mounted at `#elemacy_root`.

## Conventions (enforced or expected)

- Every PHP file starts with `defined('ABSPATH') || exit;`.
- Text domain is `elemacy` everywhere; i18n + escaping are enforced by WPCS.
- Keys use two intentional prefixes: protected post meta as `_elemacy_*` (via
  `*\Constants\MetaKeys` consts, never inline literals); options/nonces/transients/
  CPT slugs as `elemacy_*` (build dynamic ones with `Support\Utils::with_prefix()`).
  See `docs/STORAGE.md` for the full key + serialized-shape contract.
- New REST routes go through `Core\Route::*`; new AJAX through
  `Core\AjaxRouter::add_action`. Never call `register_rest_route` directly.
- All `do_action`/`apply_filters` names are constants on `Core\Hooks` — reference
  the constant, never a raw string.
- Prefer typed **DTOs/Resources** (`*/DTO/`, `*/Resources/`) over raw arrays.
- Comment only the non-obvious *why*, not the *what*.

## The free ↔ Pro seam

Pro never edits free; it hooks the extension points in `Core\Hooks` (e.g.
`REGISTER_MODULES_ACTION`, `LOADED_ACTION`, `LIBRARY_TYPES_REGISTER_ACTION`,
`POPUP_TRIGGERS_REGISTER_ACTION`, `ADMIN_SCRIPT_DATA_FILTER`). On the React side,
free exposes Slot/Fill points (`src/lib/slots.ts`) and renders its own fallback;
Pro registers fills. **Free must stay Pro-agnostic** — never read Pro state in free
code. When adding a feature Pro should be able to extend, add a `Hooks` constant or
a slot rather than a direct dependency.

## Before opening a PR

1. `vendor/bin/phpcs` is clean.
2. `npm run lint` is clean and `npm run build` succeeds.
3. New persisted data follows `docs/STORAGE.md` (native WP storage, prefixed keys,
   a `Migrator` step if you changed a serialized shape).

## Canonical services and release checks

`TemplateLibrary` owns native persistence (`DocumentWriter`, `DocumentDuplicator`, `LibraryRepository::delete`); `Core\Rendering\TemplateRenderer` owns preparation, type registration and candidate resolution. Builders contribute their own policies and placement resolvers. `Loop` owns source/query/context contracts; `Integrations/Acf` owns ACF access; internal `Modules/Forms` owns saved definitions, validation, actions and limits. Core composition uses the provider contracts; registries and supported hooks remain the extension boundaries. Avoid importing a concrete sibling builder manager.

The declared candidate pair is Free 1.0.0 + Pro 1.0.0, admin contract 1.0.0. Elementor pins are 3.35.9 and 4.2.3; PHP syntax compatibility targets 7.4+, while actual runtime results are recorded separately in RELEASE-VALIDATION.md. Do not infer that every intermediate or future version was exercised. Animations is excluded from this release; popup-native transitions remain supported.

From `src-react`, `npm run build:zip` requires Node/npm, PHP, Git and Composer, stages `scripts/package-manifest.json`, creates a production autoloader from composer.lock, and retains the ZIP/file manifest/checksum under `build/`. It fails if tooling or required files are missing. Run `python3 src-react/scripts/verify-package.py build/elemacy-1.0.0.zip --check-missing-tools` from the plugin root. Install and test that exact ZIP using setup's `--free-zip` option; never substitute a development checkout for artifact validation. The manifest records the Git revision and whether the working tree was dirty. Publishing is a separate action.
