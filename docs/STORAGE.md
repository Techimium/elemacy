# Storage model & migration contract

Elemacy and Elemacy Pro persist **only** through native WordPress storage — options,
post meta, and transients. There are **no custom tables and no raw schema**, so an
upgrade never needs DDL. The one thing that *can* force a data migration is changing
the **shape of a serialized value**. This document is the contract for those shapes:
treat each as owned by the DTO/repository listed, change shapes through a `Migrator`
step (never ad hoc), and keep every key on the `elemacy_` / `_elemacy_` prefix so the
uninstall sweeps and the migration runner stay uniform.

## Versioning & migrations

- `elemacy_db_version` (option, `OptionKeys::DB_VERSION`) holds the installed schema
  version. On every load `Elemacy::handle_version_update()` compares it to
  `ELEMACY_VERSION`: a fresh install is stamped to current with no migration; an
  upgrade runs `Core\Migrator`.
- `Core\Migrator` is an ordered `version => callback` map. Each step runs once and
  the version is stamped **after each step**, so a later failure never re-runs a
  finished step. Steps must be **idempotent**. The map is intentionally empty for
  v1 (the move onto `elemacy_library` was a pre-release clean break — no legacy data
  to re-shape).
- **When you change any serialized shape below**, add a migration keyed to the
  release that introduces the change rather than mutating data in place.

## Options

| Key | Owner | Autoload | Shape |
| --- | --- | --- | --- |
| `elemacy_active_modules` | `OptionKeys::ACTIVE_MODULES` | yes | `string[]` of module slugs (`theme-builder`, `widgets`, `custom-css`, `dynamic-tags`, `popups`). Slugs are stable identifiers — never rename. |
| `elemacy_db_version` | `OptionKeys::DB_VERSION` | yes | Version string. |
| `elemacy_library_index` | `TemplateLibrary\ResolverCache::OPTION` | yes | Resolver candidate cache: published library items' id + raw conditions only. Pure cache — deleted and lazily rebuilt on save/trash/delete; safe to drop. |
| `elemacy_<slug>_<key>` | `Core\Module::get_option()` | varies | Per-module settings (e.g. the Widgets on/off map). Build the key only via `Module::get_option/update_option`. |
| `elemacy_pro_license_key` | `License\LicenseManager` (`OPTION_KEY . '_key'`) | yes | The raw license key string. |
| `elemacy_pro_license_status` | `License\LicenseManager` (`OPTION_KEY . '_status'`) | yes | `array{ valid: bool, expires: ?string, plan: ?string, grace_start: ?int }`. Read through `get_status()`, which `wp_parse_args()`-merges defaults, so adding a key is backward-compatible; **removing or re-typing one needs a migration**. |

## Post meta — all on the single `elemacy_library` CPT

| Key | Owner | Shape |
| --- | --- | --- |
| `_elemacy_template_type` | `TemplateLibrary\Constants\MetaKeys::TEMPLATE_TYPE` | The type discriminator for **every** library item (`header`/`footer`/`single`/`archive`/`404`/`search`/`loop`/`section`/`popup`/`topbar`/`banner`/`floating`). Type names are stable identifiers. |
| `_elemacy_conditions` | `Conditions\ConditionRepository::META_KEY` | `array` of condition rules; each item validated by `Conditions\DTO\ConditionRuleDTO`. The only place display conditions are stored. |
| `_elemacy_popup_triggers` | `Modules\Popups\Constants\MetaKeys::TRIGGERS` | `array` of trigger items, each validated by `Modules\Popups\DTO\TriggerDTO` (every item carries a uuid `id`). |
| `_elemacy_popup_rules` | `Modules\Popups\Constants\MetaKeys::RULES` | `array` of rule items, each validated by `Modules\Popups\DTO\RuleDTO`. |
| `_elemacy_popup_impressions` | Pro `Extensions\Popups\Services\AnalyticsService::META_IMPRESSIONS` | `int`. Total displays. Incremented via a single atomic `meta_value = meta_value + 1` SQL UPDATE so concurrent frontend beacons can't lose a count. |
| `_elemacy_popup_conversions` | Pro `Extensions\Popups\Services\AnalyticsService::META_CONVERSIONS` | `int`. Total in-popup conversions (capped once per display client-side). Same atomic increment as impressions. |

Third-party meta (`_wp_page_template`, `_elementor_*`) is written as documented by
those plugins and is out of this contract.

## Transients

All caches; safe to drop and regenerate. Free uses the `elemacy_` prefix, Pro uses
`elemacy_pro_` (e.g. `elemacy_pro_update` for the update check, `elemacy_pro_track_*`
for per-visitor tracking dedup). Both uninstallers delete the value **and** timeout
rows for their prefix.

## Ownership & cleanup

Each plugin owns and deletes only its own keys: `elemacy/uninstall.php` removes the
`elemacy_library` posts + meta and `elemacy_%` options (excluding `elemacy_pro_%`);
`elemacy-pro/uninstall.php` removes `_elemacy_popup_analytics` and `elemacy_pro_%`.
Keep that boundary when adding keys.
