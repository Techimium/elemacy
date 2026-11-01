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

## Rules for new stored data

These rules are why a new feature normally needs no migration. Each stored name
listed here is permanent: Free's `tests/architecture/persisted-identifiers.php`
and Pro's `tests/php/run.php` (§ Stored names) fail when one stops being declared.

1. **Never rename a saved name.** Option, meta, transient and row-prefix names,
   and every stored type identifier, keep working forever. A rename ships only
   with the `Migrator` step that rewrites the stored data. *Example: a better name
   for `_elemacy_popup_triggers` is a new key plus a migration, not an edit.*
2. **Store only explicit choices.** A setting saves what an administrator changed;
   anything unsaved follows the default in code. *Example: `elemacy_active_modules`
   stores `['popups' => false]` only after someone switches Popups off, so a new
   default-on module reaches every site with no write.* The same holds for
   Elementor controls, which store nothing for a value left at its default: a
   released control's default never changes.
3. **Grow items only by additive fields with safe defaults.** A missing field reads
   as the value that keeps earlier behavior; an existing field never changes meaning
   or type without a migration. *Example: the license `Record` fills absent fields
   from defaults, so a new field needs no migration.*
4. **Store lists as `{id, type, params}`.** Each entry has a stable id, a registered
   type and a parameter bag the type owns. *Example: a new trigger option is a new
   `params` key that older popups simply lack.*
5. **One input per condition `value`.** A display condition whose rule needs more
   than one input stores the others in an additive `params` field on the rule, never
   packed into `value`. *Example: "include child terms" becomes
   `params: {children: true}`, not `value: "5|children"`, which would need a
   migration to unpack.*
6. **Caches carry a shape number.** A persisted cache stores the shape it was written
   in; any other shape is a miss and is rebuilt, never migrated. *Example:
   `elemacy_library_index` and `ResolverCache::SHAPE`.*
7. **High-volume data gets its own storage.** Data that grows with traffic or time
   (form submissions, dated statistics, event logs) is never stored as options or
   library post meta; its storage is documented here before the feature ships.
   Short-lived, self-expiring rows such as rate-limit windows and leases are exempt.

**Decision (1.0): popup analytics are lifetime totals.** Pro stores one impression
and one conversion total per popup and no event dates. Dated statistics, if they
ship later, use their own storage; totals recorded before then stay as they are
and cannot be split by date.

## Options

| Key | Owner | Autoload | Shape |
| --- | --- | --- | --- |
| `elemacy_active_modules` | `OptionKeys::ACTIVE_MODULES` | yes | `array<string, bool>` of **explicit administrator choices only**, keyed by module slug (`theme-builder`, `loop-builder`, `form-builder`, `custom-css`, `dynamic-tags`, `popups`). A module with no entry follows its own `Module::is_default_active()`, so a default-active module added later reaches existing sites; seeding writes `[]`. A stored `string[]` (the pre-release shape) reads as exactly that active set and is rewritten as a map on the next toggle. Slugs are stable identifiers — never rename; a released module's declared default is frozen. |
| `elemacy_db_version` | `OptionKeys::DB_VERSION` | yes | Version string of the release this site last ran (diagnostics and the `version_updated` hook only). |
| `elemacy_schema_version` | `OptionKeys::SCHEMA_VERSION` | no | Highest completed Free data migration (`0.0.0` while none exist); advanced only by `Core\Migrator::run()`. |
| `elemacy_schema_version_lock` | `Core\Migrator::lock_key()` via `Migrations\DatabaseLease` | no | `expiry:token` lease held while a migration runs (30 s). |
| `elemacy_library_index` | `TemplateLibrary\ResolverCache::OPTION` | no | `array{shape: int, index: array<string, array<int, array{id: int, conditions: array}>>}` — resolver candidate cache of published library items' id + raw conditions, keyed by template type. A stored value whose `shape` is not `ResolverCache::SHAPE` (or that has none) is a miss and is rebuilt, never read. Pure cache — deleted and lazily rebuilt on relevant writes; safe to drop. |
| `elemacy_library_index_generation` | `ResolverCache::GENERATION_OPTION` | yes | `int` bumped on every invalidation, so a stale concurrent rebuild discards its result. |
| `elemacy_network_seed_offset` | `Core\SiteInstaller::NETWORK_SEED_OFFSET_OPTION` | site option | `int` offset of a bounded network-activation seed still in progress; deleted when every site is seeded. |
| `elemacy_<name>` | `Support\Utils::with_prefix()` | varies | Per-module settings (e.g. `elemacy_widgets`, the per-widget on/off map written by `Widgets\Services\WidgetSettings`). Build keys only via `Utils::with_prefix()`. |
| `elemacy_form_limit_<HMAC>` | `Modules\FormBuilder\Services\SubmissionRateLimiter` | no | `expiry:count` form rate-limit window; see § Form submissions and limits. Temporary: removed on every Free uninstall. |
| `elemacy_pro_license` | Pro license SDK `Sdk\Store::option_name()` (prefix `elemacy_pro`, from `License::SLUG`) | yes | The whole license record, `Sdk\Record::to_array()`: `array{format: int, key: string, state: 'active'\|'inactive', reason: string, site: string, verified_at: int, checked_at: int, info: array{license_status, expires_at, plan_name, plan_type, seats_used, seats_limit}}`. `Record::from_stored()` fills every missing field with its default, so adding a field is backward-compatible; **removing or re-typing one needs a migration** and a `Record::FORMAT` bump (currently `1`). A record with a newer `format` keeps only its key and state. A per-site option; it would be a network option only if the SDK were configured `network_wide`, which Pro is not. |
| `elemacy_pro_schema_version` | Pro `Core\Migrator::option_key()` | no | Highest completed Pro data migration (`0.0.0` while none exist). |
| `elemacy_pro_schema_version_lock` | Pro `Core\Migrator::lock_key()` via `Migrations\DatabaseLease` | no | `expiry:token` lease held while a Pro migration runs (30 s). |
| `elemacy_pro_track_limit_<HMAC>` | Pro `Extensions\Popups\Services\TrackingRateLimiter` | no | `expiry:count` tracking rate-limit window; see § Pro counters and public tracking. Temporary. |
| `elemacy_pro_counter_lock_<post ID>` | Pro `Extensions\Popups\Services\CounterLock` | no | `expiry:token` 30-second lease taken when a counter is first created. Temporary. |

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
`elemacy_pro_`. Pro's license SDK keeps its update-check cache in the
`elemacy_pro_license_update` **site** transient (`Sdk\Store::update_name()`,
12 hours) and schedules its daily verification as the `elemacy_pro_license_check`
cron event (`Sdk\Store::check_hook()`). Both uninstallers delete the value
**and** timeout rows for their prefix; Pro's uninstall also clears the site
transient and the scheduled event through the SDK.

## Ownership & cleanup

Each plugin owns and deletes only its own keys. Every Free uninstall removes its
temporary `elemacy_form_limit_*` rows on every site. The rest of Free's removal is
opt-in: deleting Free keeps its library and settings unless `ELEMACY_REMOVE_ALL_DATA`
is defined as `true` (in `wp-config.php`), and only then does `elemacy/uninstall.php`
remove the `elemacy_library` posts + meta and `elemacy_%` options (excluding `elemacy_pro_%`);
`elemacy-pro/uninstall.php` always removes the license and temporary data
(rate-limit records, counter leases, transients and the license check's schedule),
but keeps the `_elemacy_popup_impressions` / `_elemacy_popup_conversions` meta
and the remaining `elemacy_pro_%` options (the schema version among them) unless
`ELEMACY_REMOVE_ALL_DATA` is `true`, exactly as Free keeps its library, so deleting
Pro and uploading it again loses no analytics.
Keep that boundary when adding keys.

## Form submissions and limits

Forms store nothing of their own: a form is its builder's saved data (for the Form widget, the `elemacy-form` element in the owner's `_elementor_data`), and submissions are not saved.

**No time-bound tokens in form markup.** A rendered form carries only its source, owner and form ID, the field names and the empty honeypot; no nonce, no signed token, no timestamp. A page cache may therefore keep a form page for any time, and a template edit never invalidates cached copies: each submission is checked against the owner and form as saved now. Page caches still exclude logged-in pages as usual, since a logged-in visitor's runtime config carries the nonce that proves their identity, and a submission without a valid one is processed as anonymous.

`elemacy_form_limit_<HMAC of the client IP>` is a non-autoloaded, per-site options row holding `expiry:count`. The default limit is five accepted attempts per IP across forms per 60 seconds. `elemacy/filter/forms/rate_limit` receives `['limit' => 5, 'window' => 60]` and may change both (clamped to 1-100 attempts and 1-3600 seconds). `elemacy/filter/forms/client_ip` receives `REMOTE_ADDR` and may return the visitor address a trusted proxy of the site reports; forwarding headers are never trusted otherwise. Bounded compare-and-swap retries coordinate concurrent requests, and each consumption deletes at most 20 expired owned buckets. The limiter runs after validation and before actions, and consumes an attempt even when mail fails. It does not guarantee exactly-once email delivery. Raw IP addresses are not stored.

## Pro counters and public tracking

`elemacy_pro_counter_lock_<post ID>` is a non-autoloaded, 30-second database lease. First creation rechecks under the lease; ownership and expiry fence the initialization SQL. Existing single counters use database-side increments. Expired workers cannot delete a replacement lease. Failed writes are explicit failures, never a fallback insertion.

`elemacy_pro_schema_version` advances only after a completed migration, under its own `elemacy_pro_schema_version_lock` lease (the same `DatabaseLease` Free's migrator uses). Pro has no data migrations yet, and owns both options independently of Free and license status.

Tracking accepts only `impression` and `conversion` for published, accessible popup documents with content and available server-side rules/triggers/conditions. It does not prove a human saw the popup or deduplicate all replayed events. Request input is limited to 2 KiB/four parameters. The beacon carries no nonce and no cookies: a nonce printed into a cached page expires while the page is still served, and it never kept anyone out, since a visitor can read it from the page. `elemacy_pro_track_limit_<HMAC of the client address>` enforces 120 requests per client address per minute across popups with bounded database compare-and-swap retries and expiry cleanup. The address is `REMOTE_ADDR` unless the site returns the real one from the `elemacy/filter/popups/track_client_ip` filter (forwarding headers are never trusted otherwise), and an IPv6 client is counted by its /64. The row is non-autoloaded and never stores the raw address. Recording an event updates the popup's cached counter in place; it does not evict the rest of the popup's cached meta. Cross-site inflation of the counters is a known limitation, bounded by the rate limit and by the eligibility check.

## Large-network removal

Deactivation retains data. Deleting Free retains it too, except its temporary rate-limit rows, unless `ELEMACY_REMOVE_ALL_DATA` is `true`. Pro's deletion always removes its license and temporary data, and removes its analytics counters and remaining options only under the same opt-in. Free owns library posts and Free options/meta. Both exhaust batches synchronously through their own `SiteBatchIterator`; no cron callback is scheduled after removal. Site failures are reported after attempting later sites, and blog context is always restored. Retry cleanup before deleting plugin files after a failure.

On a large network, use WP-CLI without a web request timeout before removing files: `wp plugin deactivate elemacy-pro --network` followed by `wp plugin uninstall elemacy-pro`; then, if Free's library data should also be removed, define `ELEMACY_REMOVE_ALL_DATA` as `true` and deactivate/uninstall Free the same way. Back up first because an opted-in uninstall intentionally deletes owned data. Each uninstaller works with the sibling runtime absent. Batching bounds memory, not total network execution time.
