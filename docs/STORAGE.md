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
- `Core\Migrator` runs an ordered schema-version map. Each successful step advances its own marker; a failed/incomplete step does not. Fresh installs seed the latest schema, upgrades resume pending steps, and downgrades never lower a completed marker. Free has no data migrations yet; its marker stays at `0.0.0` until the first one ships.
- Pro owns `elemacy_pro_schema_version` independently of Free and of the license; it has no data migrations yet. Migration callbacks must be idempotent and bounded. Use a new schema identifier for a new persisted shape; do not reuse a release version as an implicit migration marker.

## Options

| Key | Owner | Autoload | Shape |
| --- | --- | --- | --- |
| `elemacy_active_modules` | `OptionKeys::ACTIVE_MODULES` | yes | `array<string, bool>` of **explicit administrator choices only**, keyed by module slug (`theme-builder`, `widgets`, `custom-css`, `dynamic-tags`, `popups`). A module with no entry follows its own `Module::is_default_active()`, so a default-active module added later reaches existing sites; seeding writes `[]`. A stored `string[]` (the pre-release shape) reads as exactly that active set and is rewritten as a map on the next toggle. Slugs are stable identifiers — never rename; a released module's declared default is frozen. |
| `elemacy_db_version` | `OptionKeys::DB_VERSION` | yes | Version string of the release this site last ran (diagnostics and the `version_updated` hook only). |
| `elemacy_schema_version` | `OptionKeys::SCHEMA_VERSION` | no | Highest completed Free data migration (`0.0.0` while none exist); advanced only by `Core\Migrator::run()`. |
| `elemacy_schema_version_lock` | `Core\Migrator::lock_key()` via `Migrations\DatabaseLease` | no | `expiry:token` lease held while a migration runs (30 s). |
| `elemacy_library_index` | `TemplateLibrary\ResolverCache::OPTION` | no | `array{shape: int, index: array<string, array<int, array{id: int, conditions: array}>>}` — resolver candidate cache of published library items' id + raw conditions, keyed by template type. A stored value whose `shape` is not `ResolverCache::SHAPE` (or that has none) is a miss and is rebuilt, never read. Pure cache — deleted and lazily rebuilt on relevant writes; safe to drop. |
| `elemacy_library_index_generation` | `ResolverCache::GENERATION_OPTION` | yes | `int` bumped on every invalidation, so a stale concurrent rebuild discards its result. |
| `elemacy_network_seed_offset` | `Core\SiteInstaller::NETWORK_SEED_OFFSET_OPTION` | site option | `int` offset of a bounded network-activation seed still in progress; deleted when every site is seeded. |
| `elemacy_<name>` | `Support\Utils::with_prefix()` | varies | Per-module settings (e.g. `elemacy_widgets`, the Widgets on/off map written by `WidgetService`). Build keys only via `Utils::with_prefix()`. |
| `elemacy_pro_license_key` | `License\LicenseManager` (`OPTION_KEY . '_key'`) | yes | The raw license key string. |
| `elemacy_pro_license_status` | `License\LicenseManager` (`OPTION_KEY . '_status'`) | yes | `array{ valid: bool, expires: ?string, plan: ?string }`. Read through `get_status()`, which `wp_parse_args()`-merges defaults, so adding a key is backward-compatible; **removing or re-typing one needs a migration**. A `grace_start` key left by a pre-release build is ignored. |

## Post meta — all on the single `elemacy_library` CPT

| Key | Owner | Shape |
| --- | --- | --- |
| `_elemacy_template_type` | `TemplateLibrary\Constants\MetaKeys::TEMPLATE_TYPE` | The type discriminator for **every** library item (`header`/`footer`/`single`/`archive`/`404`/`search`/`loop`/`section`/`popup`/`topbar`/`banner`/`floating`). Type names are stable identifiers. |
| `_elemacy_conditions` | `Conditions\ConditionRepository::META_KEY` | `array` of condition rules; each item validated by `Conditions\DTO\ConditionRuleDTO`. The only place display conditions are stored. |
| `_elemacy_popup_triggers` | `Modules\Popups\Constants\MetaKeys::TRIGGERS` | `array` of trigger items, each validated by `Modules\Popups\DTO\TriggerDTO`. Every item carries a string `id`: the admin app's own (`trigger_<time>_<random>`), or a uuid the server fills in when none was sent. The `type` must be a registered trigger (a Free placeholder for a Pro type counts), and text `params` are stored without altering percent-encoded sequences. |
| `_elemacy_popup_rules` | `Modules\Popups\Constants\MetaKeys::RULES` | `array` of rule items, each validated by `Modules\Popups\DTO\RuleDTO`. Each item's `params` bag is owned by the rule type's `get_config_schema()`; `datetime` params (e.g. pro's schedule) are stored as timezone-naive `YYYY-MM-DDTHH:mm` strings — the evaluating side owns the timezone interpretation, so changing that is a shape change requiring a migration. |
| `_elemacy_popup_impressions` | Pro `Extensions\Popups\Services\AnalyticsService::META_IMPRESSIONS` | `int`. Total displays. Incremented via a single atomic `meta_value = meta_value + 1` SQL UPDATE so concurrent frontend beacons can't lose a count. |
| `_elemacy_popup_conversions` | Pro `Extensions\Popups\Services\AnalyticsService::META_CONVERSIONS` | `int`. Total in-popup conversions (capped once per display client-side). Same atomic increment as impressions. |

Third-party meta (`_wp_page_template`, `_elementor_*`) is written as documented by
those plugins and is out of this contract.

## Transients

All caches; safe to drop and regenerate. Free uses the `elemacy_` prefix
(`elemacy_library_index_rebuild_lock`, a 30-second rebuild mutex), Pro uses
`elemacy_pro_` (e.g. `elemacy_pro_update` for the update-check cache). Both
uninstallers delete the value **and** timeout rows for their prefix.

## Ownership & cleanup

Each plugin owns and deletes only its own keys. Free's removal is opt-in: deleting
Free keeps everything unless `ELEMACY_REMOVE_ALL_DATA` is defined as `true` (in
`wp-config.php`), and only then does `elemacy/uninstall.php` remove the
`elemacy_library` posts + meta and `elemacy_%` options (excluding `elemacy_pro_%`);
`elemacy-pro/uninstall.php` always removes the license and temporary data
(rate-limit records, counter leases, transients and the license check's schedule),
but keeps the `_elemacy_popup_impressions` / `_elemacy_popup_conversions` meta
and the remaining `elemacy_pro_%` options (the schema version among them) unless
`ELEMACY_REMOVE_ALL_DATA` is `true`, exactly as Free keeps its library, so deleting
Pro and uploading it again loses no analytics.
Keep that boundary when adding keys.

## Form submission context and limits

Saved library forms emit `elemacy_form_context`, a purpose-specific HMAC proof bound to site, visitor user ID, widget, owner, host query, placement and current authoring metadata. It expires after 24 hours; live host accessibility and placement are checked again on submission. Form widgets use Elementor's dynamic-content path so element caching cannot share the proof between hosts or users. External page caches must exclude logged-in pages and expire guest form pages before their nonce/proof expires. Refresh stale pages after template edits or salt rotation; a stale proof fails closed and asks the visitor to refresh.

`elemacy_form_limit_<HMAC of IP>` is a non-autoloaded, per-site options row holding `expiry:count`. The default limit remains five accepted attempts per IP across forms per 60 seconds. Bounded compare-and-swap retries coordinate concurrent requests; each consumption deletes at most 20 expired owned buckets. The limiter runs before actions and consumes an attempt even when mail fails. It does not guarantee exactly-once email delivery. Raw IP addresses are not stored.

## Pro counters and public tracking

`elemacy_pro_counter_lock_<post ID>` is a non-autoloaded, 30-second database lease. First creation rechecks under the lease; ownership and expiry fence the initialization SQL. Existing single counters use database-side increments. Expired workers cannot delete a replacement lease. Failed writes are explicit failures, never a fallback insertion.

`elemacy_pro_schema_version` advances only after a completed migration, under its own `elemacy_pro_schema_version_lock` lease (the same `DatabaseLease` Free's migrator uses). Pro has no data migrations yet, and owns both options independently of Free and license status.

Tracking accepts only `impression` and `conversion` for published, accessible popup documents with content and available server-side rules/triggers/conditions. It does not prove a human saw the popup or deduplicate all replayed events. Request input is limited to 2 KiB/four parameters. The beacon carries no nonce and no cookies: a nonce printed into a cached page expires while the page is still served, and it never kept anyone out, since a visitor can read it from the page. `elemacy_pro_track_limit_<HMAC of the client address>` enforces 120 requests per client address per minute across popups with bounded database compare-and-swap retries and expiry cleanup. The address is `REMOTE_ADDR` unless the site returns the real one from the `elemacy/filter/popups/track_client_ip` filter (forwarding headers are never trusted otherwise), and an IPv6 client is counted by its /64. The row is non-autoloaded and never stores the raw address. Recording an event updates the popup's cached counter in place; it does not evict the rest of the popup's cached meta. Cross-site inflation of the counters is a known limitation, bounded by the rate limit and by the eligibility check.

## Large-network removal

Deactivation retains data. Deleting Free retains it too unless `ELEMACY_REMOVE_ALL_DATA` is `true`. Pro's deletion always removes its license and temporary data, and removes its analytics counters and remaining options only under the same opt-in. Free owns library posts and Free options/meta. Both exhaust batches synchronously through their own `SiteBatchIterator`; no cron callback is scheduled after removal. Site failures are reported after attempting later sites, and blog context is always restored. Retry cleanup before deleting plugin files after a failure.

On a large network, use WP-CLI without a web request timeout before removing files: `wp plugin deactivate elemacy-pro --network` followed by `wp plugin uninstall elemacy-pro`; then, if Free's library data should also be removed, define `ELEMACY_REMOVE_ALL_DATA` as `true` and deactivate/uninstall Free the same way. Back up first because an opted-in uninstall intentionally deletes owned data. Each uninstaller works with the sibling runtime absent. Batching bounds memory, not total network execution time.
