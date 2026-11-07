# Contributing to Elemacy

Elemacy is a free WordPress plugin that extends Elementor. The paid add-on, **Elemacy Pro**, is a separate plugin that extends Free through the seams documented here; Free never knows about Pro.

Read `docs/ARCHITECTURE.md` first: it is the map of the codebase. `docs/CONTRACTS.md` lists what must never be renamed, `docs/STORAGE.md` every stored shape, `docs/ELEMENTOR-COMPATIBILITY.md` every Elementor API we touch, and `docs/RELEASE.md` how a release is verified.

## Prerequisites

- A local WordPress install with **Elementor** active.
- PHP 7.4+ and Composer.
- Node 20.19+ (or 22.12+) and npm.
- For the real-site suites: MySQL (`mysqld`), WP-CLI and Python 3.

## Setup

```bash
composer install          # plugin root: PHP dev tooling (WPCS, PHPUnit)
cd src-react && npm ci    # admin app
```

For a live admin UI with hot reload add `define('ELEMACY_ENV', 'dev');` to `wp-config.php` before the plugin loads and run `npm run dev` in `src-react/`. Without it the admin app loads the built bundle from `assets/admin/`.

## Commands

| Where | Command | Purpose |
| --- | --- | --- |
| plugin root | `php tests/php/run.php` | Fast harness (pure logic, stubbed WordPress). |
| plugin root | `vendor/bin/phpcs -q` | Coding standards and PHP compatibility. Target 0 errors. |
| plugin root | `vendor/bin/phpcbf` | Auto-fix what WPCS can fix. |
| plugin root | `php tests/architecture/check.php` | Layering, Elementor boundary, planning-reference, contract and layout rules. |
| plugin root | `php tests/integration/run.php` | Real WordPress and Elementor suite in a disposable site (`tests/integration/README.md`). |
| `tests/e2e/` | `node run-*.mjs` | Real-browser suites (`tests/e2e/README.md`). |
| `src-react/` | `npm run dev` / `npm run build` / `npm run lint` | Admin app. |
| `src-react/` | `npm run build:zip` | Release archive (`docs/RELEASE.md`). |

The full gate, in order, is in `docs/RELEASE.md` section 1. Run it before every pull request that touches PHP.

## Where things live

Every file has one home, and `php tests/architecture/check.php` (rule 9) rejects anything else. The full rule is `docs/ARCHITECTURE.md` § Where files go.

| Folder | What goes there |
| --- | --- |
| `src/Core/` | Plugin lifecycle and composition: bootstrap, `Module`, `ModuleManager`, `Container`, `Hooks`, migrations, multisite, capabilities, admin menu and scripts. |
| `src/Http/` | REST `Route`, `AjaxRouter`, requests, responses, middlewares, validation, exceptions. |
| `src/Elementor/` | Adapters to Elementor's APIs: rendering, CSS, preview, documents, controls, compatibility probes. |
| `src/Widgets/` | The always-on widget platform (`BaseWidget`, the registry, per-widget switches, the Widgets screen) and the general widgets, one folder each. |
| `src/Loop/`, `src/Conditions/`, `src/TemplateLibrary/`, `src/Integrations/` | Shared engines that two or more areas use. |
| `src/Modules/<Feature>/` | A feature an administrator can switch off, with everything only it uses: its engine, widgets, documents, routes, config and assets. |
| `src/Support/` | Small stateless helpers. |
| `src/Config/` | The composition root only: `modules.php` and the shared layers' `api.php`. Every other list lives in its owner's `Config/`. |

Code goes in a shared layer only when two or more areas use it; otherwise it lives in the one module that does. Shared layers never import a module, and a module never imports another module.

Inside any area the same role folders hold the same things: `Config/`, `Contracts/` (`*Interface`), `Controllers/` (`*Controller`), `DTO/` (`*DTO`), `Documents/`, `Exceptions/` (`*Exception`), `Middlewares/` (`*Middleware`), `Placeholders/`, `Requests/`, `Resources/`, `Services/`, `Support/`, `Views/`, `Widgets/` and `assets/{js,css,images}/`. A class's suffix decides its folder, and folders of PHP are PascalCase.

## Conventions

- Every PHP file starts with `defined('ABSPATH') || exit;`.
- Text domain is `elemacy`; i18n and escaping are enforced by WPCS.
- Keys: protected post meta as `_elemacy_*` through `*\Constants\MetaKeys`; options, nonces, transients and CPT slugs as `elemacy_*` (dynamic ones through `Support\Utils::with_prefix()`). Shapes live in `docs/STORAGE.md`; changing one needs a `Migrator` step.
- Hook names are constants on `Core\Hooks`. Never a raw string.
- REST routes go through `Http\Route` and are protected by middleware (`AdminMiddleware` for administrative routes); a route without middleware is public. AJAX goes through `Http\AjaxRouter` with `for_public()`, `for_guest()`, `for_authenticated()` or `require_capability()`. Never call `register_rest_route` directly.
- Anything the container builds (controllers, services) takes its dependencies as required constructor parameters. `Container::instance()` is used only inside classes Elementor or WordPress instantiate (widgets, documents, dynamic tags, hook callbacks) and in bootstrap.
- Registries keep their `::instance()` and are also bound in the container by their owner's `register()`.
- Prefer typed DTOs and Resources over raw arrays.
- A docblock is one sentence of what and, when needed, one sentence of why. Longer reasoning goes into `docs/ARCHITECTURE.md`, referenced by section name. Never reference a planning document, a task number or a change name in code.

## Recipes

### Add a module

1. Create `src/Modules/Reviews/Reviews.php`:

```php
namespace Elemacy\Modules\Reviews;

use Elemacy\Core\Contracts\ContainerInterface;
use Elemacy\Core\Module;
use Elemacy\Support\Utils;

class Reviews extends Module
{
    public function get_name(): string { return 'reviews'; }
    public function get_title(): string { return __('Reviews', 'elemacy'); }
    public function get_icon(): string { return 'star'; }
    public function get_description(): string { return __('Collect and display reviews.', 'elemacy'); }

    public function register(ContainerInterface $container): void
    {
        // Bind what other code may inject; registries bind to their ::instance().
    }

    public function boot(): void
    {
        // Attach hooks. Anything with a translated label hooks `init`.
    }

    public function register_routes()
    {
        require_once Utils::get_plugin_path('src/Modules/Reviews/Config/api.php');
    }

    public function get_widgets(): array
    {
        return require Utils::get_plugin_path('src/Modules/Reviews/Config/widgets.php');
    }
}
```

2. Add `Reviews::class` to `src/Config/modules.php`. The module appears on the Modules screen, can be toggled, and its `register()` runs before any module's `boot()`. Its slug is stored in `elemacy_active_modules`, so it is never renamed after release.

### Add a widget

1. Pick its owner. A widget of a feature module lives in `src/Modules/<Feature>/Widgets/Testimonial/`; a general widget that belongs to no feature lives in `src/Widgets/Testimonial/`.
2. Create `Testimonial/Testimonial.php` extending `Elemacy\Widgets\BaseWidget`, with `get_name()` returning a stable, never-renamed name such as `elemacy-testimonial`. Big control sections go in `Testimonial/Controls/`, helpers beside the class, and its own sources in `Testimonial/assets/js/testimonial.js` and `assets/css/testimonial.css`.
3. List it in the owner's `Config/widgets.php` (`name`, `title`, `icon`, `class`, optional `requires`): `src/Widgets/Config/widgets.php` for the platform, or the module's own file returned by `get_widgets()`. Add-ons use `Hooks::WIDGETS_REGISTER_FILTER`.
4. Register its handles from the owner's `Services/FrontendAssets.php` with `Widgets\Support\WidgetAssets::register_script()` / `register_style()`, which resolve the widget folder's `assets/js` and `assets/css`. The handle is `elemacy-<file>`.

The widget is offered in the editor and listed on the Widgets screen while its owner is available and its own switch is on. Deleting its folder and its list entry removes it completely.

### Add a loop data source

1. Keep the query in an Elementor-free class (like `Loop/Sources/Posts/PostsQuery.php`), and the public-context policy in another (like `Loop/Sources/Posts/PostsPolicy.php`) implementing `Loop\Contracts\PublicLoopSourcePolicyInterface`.
2. Create the source under `Modules/LoopBuilder/DataSources/` implementing `Loop\Contracts\LoopDataSourceInterface`: `get_key()`, `get_label()`, `register_controls(BaseWidget $widget)`, `get_items(array $settings): LoopResultDTO`, `sanitize_ajax_settings()`, `register_preview_controls()`, `resolve_preview_item()`. Also implement `PublicLoopSourcePolicyInterface` if visitors may see it; without it the source renders only for editors.
3. Register it on `Hooks::LOOP_DATA_SOURCES_REGISTER_ACTION` with `Loop\LoopDataSourceRegistry`. A source whose items are the host page's own main query and whose pages are that page's URLs (like `CurrentQueryDataSource`) also implements `Loop\Contracts\PageUrlPaginatedSourceInterface`, so the signed-definition endpoint refuses it. The Loop Grid, Loop Carousel, AJAX pagination and Loop Item preview pick it up without changes.

### Add a display condition

1. Create a class extending `Conditions\BaseCondition` with `get_name()` (`group/name`, never renamed), `get_type()`, `get_label()`, `get_value_type()` and `check(ConditionRuleDTO $rule): bool`.
2. Free conditions are listed in `src/Conditions/Config/conditions.php`; add-ons register on `Hooks::CONDITIONS_REGISTER_ACTION`.
3. Conditions are flat: includes OR together, any matching exclude vetoes. Do not design a condition that assumes grouping.

### Add a popup rule or trigger

1. Extend `Modules\Popups\Rules\BaseRule` or `Modules\Popups\Triggers\BaseTrigger` and implement its interface: the stable name, the label, `get_config_schema()` (field types `number`, `text`, `datetime`, `select`, `boolean`, `checkbox-group`, rendered by the admin UI without React changes), and `check()` for a server-evaluable rule or `to_js_config()` for the frontend engine.
2. Register on `Hooks::POPUP_RULES_REGISTER_ACTION` or `Hooks::POPUP_TRIGGERS_REGISTER_ACTION`. A rule with no frontend handler is reported as unavailable, and a popup that requires it is suspended rather than shown to everyone.

### Add a REST endpoint

In the module's `Config/api.php`:

```php
use Elemacy\Http\Middlewares\AdminMiddleware;
use Elemacy\Http\Route;
use Elemacy\Modules\Reviews\Controllers\ReviewController;

Route::get('/reviews', [ReviewController::class, 'index'])
    ->middleware(AdminMiddleware::class);
```

The controller receives its services through its constructor. Throw `Http\Exceptions\ValidationException` for bad input; the transport boundary turns every `Throwable` into the safe response envelope. A public endpoint attaches no access middleware and enforces its own application policy; a nonce is not authorization. Frontend AJAX uses `Http\AjaxRouter::add_action(...)->for_public()->with_nonce(...)` in `Config/ajax.php`.

## The Free and Pro seam

Pro never edits Free. It subscribes a provider through `Hooks::REGISTER_PROVIDERS_ACTION`, registers into the domain registries on `Hooks::LOADED_ACTION` and the `*_REGISTER_ACTION` hooks, and adds admin UI through Slot/Fill points (`src-react/src/lib/slots.ts`). When a feature should be extendable, add a `Hooks` constant or a slot rather than a direct dependency.

## Before opening a pull request

Every feature addition or behavior change must keep automated coverage in sync. List affected feature/journey IDs from `tests/coverage/inventory.json`, update meaningful UI-to-save-to-visitor assertions (see `tests/e2e/JOURNEYS.md`), and run the affected cases plus existing regressions. Changes to an existing identifier still need test-impact review; a new test file is not required for a pure refactor when unchanged behavior is already covered.

When declarations or runners change, update the inventory and run `python3 tests/coverage/check.py --write-roadmap`, then `python3 tests/coverage/check.py`. Record commands, artifact/revision pair, profiles, reports and any blocked prerequisites in the PR. A mapped/pending entry, skip or retry pass is not release evidence. The current journey suite is incomplete; retain the full existing release checks.

Examples: a new popup trigger needs a catalog entry and positive/negative UI-to-visitor cases; a changed Header save behavior updates the existing Header journey even though its ID is unchanged; a helper extraction that preserves behavior may reuse the same cases and record their passing results.

1. `vendor/bin/phpcs -q`, `php tests/php/run.php` and `php tests/architecture/check.php` pass.
2. `npm run lint` and `npm run build` succeed in `src-react/`.
3. Behaviour changes carry an integration or browser test; the full gate in `docs/RELEASE.md` passes.
4. New persisted data follows `docs/STORAGE.md`; new identifiers are added to `docs/CONTRACTS.md`.
