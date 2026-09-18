# Storage model & migration contract

Elemacy and Elemacy Pro persist **only** through native WordPress storage — options,
post meta, and transients. There are **no custom tables and no raw schema**, so an
upgrade never needs DDL. The one thing that *can* force a data migration is changing
the **shape of a serialized value**. This document is the contract for those shapes:
treat each as owned by the DTO/repository listed, change shapes through a `Migrator`
step (never ad hoc), and keep every key on the `elemacy_` / `_elemacy_` prefix so the
uninstall sweeps and the migration runner stay uniform.

## Versioning & migrations

- `elemacy_db_version` records the installed release version. `elemacy_schema_version` independently records completed Free data migrations; release numbers never infer a completed data transformation.
- `Core\Migrator` runs an ordered schema-version map. Each successful step advances its own marker; a failed/incomplete step does not. Fresh installs seed the latest schema, upgrades resume pending steps, and downgrades never lower a completed marker. Free currently has no data-transforming steps.
- Pro owns `elemacy_pro_schema_version` and its schema `1` counter migration independently. Migration callbacks must be idempotent and bounded. Use a new schema identifier for a new persisted shape; do not reuse a release version as an implicit migration marker.

## Options

| Key | Owner | Autoload | Shape |
| --- | --- | --- | --- |
| `elemacy_active_modules` | `OptionKeys::ACTIVE_MODULES` | yes | `string[]` of module slugs (`theme-builder`, `widgets`, `custom-css`, `dynamic-tags`, `popups`). Slugs are stable identifiers — never rename. |
| `elemacy_db_version` | `OptionKeys::DB_VERSION` | yes | Version string. |
| `elemacy_library_index` | `TemplateLibrary\ResolverCache::OPTION` | no | Resolver candidate cache: published library items' id + raw conditions only. Pure cache — deleted and lazily rebuilt on save/trash/delete; safe to drop. |
| `elemacy_<name>` | `Support\Utils::with_prefix()` | varies | Per-module settings (e.g. `elemacy_widgets`, the Widgets on/off map written by `WidgetService`). Build keys only via `Utils::with_prefix()`. |
| `elemacy_pro_license_key` | `License\LicenseManager` (`OPTION_KEY . '_key'`) | yes | The raw license key string. |
| `elemacy_pro_license_status` | `License\LicenseManager` (`OPTION_KEY . '_status'`) | yes | `array{ valid: bool, expires: ?string, plan: ?string, grace_start: ?int }`. Read through `get_status()`, which `wp_parse_args()`-merges defaults, so adding a key is backward-compatible; **removing or re-typing one needs a migration**. |

## Post meta — all on the single `elemacy_library` CPT

| Key | Owner | Shape |
| --- | --- | --- |
| `_elemacy_template_type` | `TemplateLibrary\Constants\MetaKeys::TEMPLATE_TYPE` | The type discriminator for **every** library item (`header`/`footer`/`single`/`archive`/`404`/`search`/`loop`/`section`/`popup`/`topbar`/`banner`/`floating`). Type names are stable identifiers. |
| `_elemacy_conditions` | `Conditions\ConditionRepository::META_KEY` | `array` of condition rules; each item validated by `Conditions\DTO\ConditionRuleDTO`. The only place display conditions are stored. |
| `_elemacy_popup_triggers` | `Modules\Popups\Constants\MetaKeys::TRIGGERS` | `array` of trigger items, each validated by `Modules\Popups\DTO\TriggerDTO` (every item carries a uuid `id`). |
| `_elemacy_popup_rules` | `Modules\Popups\Constants\MetaKeys::RULES` | `array` of rule items, each validated by `Modules\Popups\DTO\RuleDTO`. Each item's `params` bag is owned by the rule type's `get_config_schema()`; `datetime` params (e.g. pro's schedule) are stored as timezone-naive `YYYY-MM-DDTHH:mm` strings — the evaluating side owns the timezone interpretation, so changing that is a shape change requiring a migration. |
| `_elemacy_popup_impressions` | Pro `Extensions\Popups\Services\AnalyticsService::META_IMPRESSIONS` | `int`. Total displays. Incremented via a single atomic `meta_value = meta_value + 1` SQL UPDATE so concurrent frontend beacons can't lose a count. |
| `_elemacy_popup_conversions` | Pro `Extensions\Popups\Services\AnalyticsService::META_CONVERSIONS` | `int`. Total in-popup conversions (capped once per display client-side). Same atomic increment as impressions. |

Third-party meta (`_wp_page_template`, `_elementor_*`) is written as documented by
those plugins and is out of this contract.

## Transients

All caches; safe to drop and regenerate. Free uses the `elemacy_` prefix (e.g.
legacy `elemacy_form_throttle_*` rows; current forms use the options-based limiter below), Pro uses
`elemacy_pro_` (e.g. `elemacy_pro_update` for the update-check cache). Both
uninstallers delete the value **and** timeout rows for their prefix.

## Ownership & cleanup

Each plugin owns and deletes only its own keys: `elemacy/uninstall.php` removes the
`elemacy_library` posts + meta and `elemacy_%` options (excluding `elemacy_pro_%`);
`elemacy-pro/uninstall.php` removes the `_elemacy_popup_impressions` /
`_elemacy_popup_conversions` meta and `elemacy_pro_%` options + transients.
Keep that boundary when adding keys.

## Form submission context and limits

Saved library forms emit `elemacy_form_context`, a purpose-specific HMAC proof bound to site, visitor user ID, widget, owner, host query, placement and current authoring metadata. It expires after 24 hours; live host accessibility and placement are checked again on submission. Form widgets use Elementor's dynamic-content path so element caching cannot share the proof between hosts or users. External page caches must exclude logged-in pages and expire guest form pages before their nonce/proof expires. Refresh stale pages after template edits or salt rotation; a stale proof fails closed and asks the visitor to refresh.

`elemacy_form_limit_<HMAC of IP>` is a non-autoloaded, per-site options row holding `expiry:count`. The default limit remains five accepted attempts per IP across forms per 60 seconds. Bounded compare-and-swap retries coordinate concurrent requests; each consumption deletes at most 20 expired owned buckets. The limiter runs before actions and consumes an attempt even when mail fails. It does not guarantee exactly-once email delivery. Raw IP addresses are not stored.

## Pro counter normalization and public tracking

Pro schema `1` normalizes legacy counter rows in resumable batches of 50 post IDs. The lowest `meta_id` remains authoritative. Each stored value is interpreted as a nonnegative integer; the largest legacy value is the baseline, because duplicate rows were replicas incremented together, not independent event ledgers. Their values are not summed. The missing baseline is added atomically before deleting the other rows, making an interrupted cleanup repeatable. New tracking requests encountering duplicates normalize them under the same coordination boundary before incrementing, so accepted concurrent events are retained.

`elemacy_pro_counter_lock_<post ID>` is a non-autoloaded, 30-second database lease. First creation rechecks under the lease; ownership and expiry fence initialization and normalization SQL. Existing single counters use database-side increments. Expired workers cannot delete a replacement lease. Failed writes are explicit failures, never a fallback insertion.

`elemacy_pro_counter_migration_cursor` records successful progress; `elemacy_pro_schema_version` advances only after the final batch. Busy/failed items retry on later requests. Pro owns both independently of Free and license status.

Tracking accepts only `impression` and `conversion` for published, accessible popup documents with content and available server-side rules/triggers/conditions. It does not prove a human saw the popup or deduplicate all replayed events. Request input is limited to 2 KiB/four parameters; a nonce is hygiene, not authorization. `elemacy_pro_track_limit_<HMAC of IP>` enforces 120 requests per IP per minute across popups with bounded database compare-and-swap retries and expiry cleanup. It is non-autoloaded and never stores the raw IP.

## Large-network removal

Deactivation retains data. Deletion retains the existing destructive uninstall policy: Free owns library posts and Free options/meta, while Pro owns its license/settings and counter metadata. Both exhaust batches synchronously through their own `SiteBatchIterator`; no cron callback is scheduled after removal. Site failures are reported after attempting later sites, and blog context is always restored. Retry cleanup before deleting plugin files after a failure.

On a large network, use WP-CLI without a web request timeout before removing files: `wp plugin deactivate elemacy-pro --network` followed by `wp plugin uninstall elemacy-pro`; then deactivate/uninstall Free the same way if its library data should also be removed. Back up first because uninstall intentionally deletes owned data. Each uninstaller works with the sibling runtime absent. Batching bounds memory, not total network execution time.
