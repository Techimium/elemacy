# Architecture contracts — first-release baseline

Recorded 2026-09-07 for `harden-first-release-architecture`, task 0.1. This is an inventory of the existing implementation, not a claim that its current authorization or lifecycle behavior is correct. The approved change identifies intentional security corrections separately.

`F/` means the Elemacy plugin root; `P/` means the sibling Elemacy Pro root. Source paths without either prefix are relative to F. No stored identifier below is renamed by a directory move. Later phases must append the canonical move map and compatibility window while retaining this baseline.

## Repository state before implementation

| Repository | HEAD | Working tree |
| --- | --- | --- |
| Elemacy | `f8d227a5b7a7c166b0aa00e2e947955f65d3ca9d` | Clean; `git status --porcelain=v1 --untracked-files=all` returned no entries |
| Elemacy Pro | `5659ea5a5f06ac64eb51b450fd6d344558e316e9` | Clean; same command returned no entries |

Both Git commands exited successfully. The environment also emitted `Failed to create stream fd: Operation not permitted` on stderr; the revision and status results above were obtained nonetheless. OpenSpec artifacts are outside these nested plugin repositories. No commit, reset, branch change or user-file replacement was performed for this inventory.

## Storage ownership

| Key or identifier | Existing owner / contract |
| --- | --- |
| `elemacy_library` | `src/TemplateLibrary/LibraryPostType.php`; one shared CPT, publicly queryable, no core `wp/v2` controller; writes require `manage_options` |
| `elemacy-library` | Library rewrite slug |
| `_elemacy_template_type` | `src/TemplateLibrary/Constants/MetaKeys.php`; string type discriminator |
| `_elemacy_conditions` | `src/Conditions/ConditionRepository.php`; array of `{id, type, operator, value}`; operator `include` or `exclude` |
| `_elemacy_popup_triggers` | `src/Modules/Popups/Constants/MetaKeys.php`; array of `{id, type, params}` |
| `_elemacy_popup_rules` | Same owner and shape as triggers, with rule-owned parameter values |
| `_elemacy_popup_impressions` | Pro `Extensions/Popups/Services/AnalyticsService.php`; impression counter |
| `_elemacy_popup_conversions` | Same Pro owner; conversion counter |
| `elemacy_active_modules` | `src/Core/Constants/OptionKeys.php`; array of module slugs |
| `elemacy_db_version` | Same owner; currently a release-version string, despite its schema-oriented name |
| `elemacy_library_index` | `src/TemplateLibrary/ResolverCache.php`; rebuildable published candidate index, **non-autoloaded in code** |
| `elemacy_widgets` | `src/Modules/Widgets/Services/WidgetService.php`; widget status map, built through `Utils::with_prefix('widgets')` |
| `elemacy_pro_license_key` | Pro `Core/License/LicenseManager.php`; stored license key; never include its value in fixtures/logs |
| `elemacy_pro_license_status` | Same owner; `{valid, expires, plan, grace_start}` with defaults supplied on read |
| `elemacy_form_throttle_*` | Free form submission transient prefix |
| `elemacy_pro_update` | Pro updater transient/cache identifier |
| `elemacy_pro_license_revalidate` | Pro license cron hook; existing daily revalidation |

Elementor authoring metadata is also a compatibility boundary: `_elementor_data`, `_elementor_edit_mode`, `_elementor_template_type`, `_elementor_page_settings`, plus Elementor-owned generated asset metadata such as `_elementor_css`. Native `_wp_page_template` also participates in document behavior. Do not blindly copy all metadata during duplication; phase 5 distinguishes authoring values from generated runtime state.

`docs/STORAGE.md` currently describes `elemacy_library_index` as autoloaded. The implementation explicitly writes it with autoload disabled; retain that behavior. Its claim about atomic analytics increments covers an existing row, not race-free first initialization. The final documentation phase will reconcile those descriptions.

## Saved module, widget and data-source names

Enabled Free module slugs: `theme-builder`, `widgets`, `custom-css`, `dynamic-tags`, `popups`. Source: `src/Config/modules.php` and each module's `get_name()`. The Animations placeholder currently appears in the catalog; only its release registration/packaging is in scope, and it must not ship as an available first-release feature.

| Widget class under `Modules/Widgets/Widgets` | Stored Elementor widget name |
| --- | --- |
| `SiteLogo` | `elemacy-site-logo` |
| `NavMenu` | `elemacy_nav_menu` |
| `LoopGrid` | `elemacy-loop-grid` |
| `LoopCarousel` | `elemacy-loop-carousel` |
| `Form` | `elemacy-form` |
| `Search` | `elemacy-search` |
| `Acf/AcfAccordion` | `elemacy-acf-accordion` |
| `Acf/AcfIconList` | `elemacy-acf-icon-list` |
| `Acf/AcfGallery` | `elemacy-acf-gallery` |

The catalog authority is `src/Modules/Widgets/Config/widgets.php`. Preserve the intentional underscore in `elemacy_nav_menu`.

| Data-source implementation | Saved `data_source` value |
| --- | --- |
| Free `PostsDataSource` | `posts` |
| Free `TaxonomyDataSource` | `terms` |
| Free `UsersDataSource` | `users` |
| Pro `AcfRepeaterDataSource` | `acf_repeater` |

The same registration contributes query controls and standalone preview. `template_id`, the Elementor element `id`, source query settings and `preview_data_source` remain saved document values; the pagination refactor changes which request values are authoritative, not those stored names. Loop Carousel currently has no AJAX pagination feature.

## Library types and Elementor document names

| Library group | Stored library type | Elementor document type |
| --- | --- | --- |
| `block` | `section` | `wp-page` |
| `block` | `loop` | `elemacy_loop` |
| `theme` | `header`, `footer`, `404` | `wp-page` fallback |
| `theme` | `single` | `elemacy_single` |
| `theme` | `archive` | `elemacy_archive` |
| `theme` | `search` | `elemacy_search` |
| `popup` | `popup`, `topbar`, `banner`, `floating` | `elemacy_popup` |

Sources: `Core/Elemacy.php`, builder module registrations, `Modules/{ThemeBuilder,Widgets}/Config/documents.php`, their document classes, and `Modules/Popups/Documents/PopupDocument.php`. Fix unsynchronized writes without changing these values. Theme content-location order is `404` (5), `single` (10), `search` (15), `archive` (20); header/footer are separate placements.

**Phase 5 update (unchanged values, new canonical resolution path):** every mapping in the table above is unchanged and re-verified by `tests/integration/library-persistence.php::testStoredLibraryTypesStillMapToTheDocumentedElementorDocumentTypes`. `TemplateLibrary/TypeDefinition::$document_class` (task 5.2/5.3) is now what `BlockTemplateService`/`TemplateService`/`PopupService` (via `DocumentWriter::document_type()`) resolve a type's Elementor document type from, rather than each service reaching into another module's `Config/documents.php` directly. Each module's own `Config/documents.php` still exists and is still read by that module's own `Documents/DocumentManager` to register the document class with Elementor's `Documents_Manager` — a within-module concern this phase left alone. Popups have no per-type `document_class` in the registry at all — every popup type resolves to the single constant `PopupService::DOCUMENT_TYPE` (`elemacy_popup`), passed explicitly to `DocumentWriter`/`DocumentDuplicator` rather than looked up per type.

## Popup targeting and display settings

Trigger identifiers: `page_load`, `click`, `scroll`, `exit_intent`, `inactivity`, `scroll_to_element`, `on_popup_close`.

Rule identifiers: `frequency_cap`, `logged_in`, `once_per`, `devices`, `schedule`, `page_views`, `sessions`, `referrer`, `roles`, `browser`.

Free supplies real page-load/click triggers and frequency/logged-in rules; Pro supplies implementations for the corresponding Free mocks. Source registries and `get_name()` values, rather than translated labels, identify stored configuration. Rule/trigger `params` retain their type-owned shapes. Schedule datetime values currently use timezone-naive `YYYY-MM-DDTHH:mm` strings.

The following `DisplayKeys` values are stored in Elementor page settings and consumed by `DocumentDisplay`. Their authority is `src/Modules/Popups/Support/DisplayKeys.php`; defaults are in `DisplayDefaults.php`:

`elemacy_position`, `elemacy_sticky`, `elemacy_width`, `elemacy_z_index`, `elemacy_offset_x`, `elemacy_offset_y`, `elemacy_overlay_color`, `elemacy_overlay_opacity`, `elemacy_overlay_blur`, `elemacy_animation_in`, `elemacy_animation_out`, `elemacy_close_button`, `elemacy_auto_close`, `elemacy_close_on_overlay`, `elemacy_close_on_esc`, `elemacy_prevent_scroll`.

Responsive variants and legacy display settings must continue following `DocumentDisplay` and the existing popup positioning spec. `elemacy_animation_in/out` here belong to native popup transitions, not the excluded Animations module.

Condition identifiers come from `src/Conditions/*/get_name()` and Pro's matching implementations: `general/entire_site`, `general/front_page`, `general/not_found`, `general/search_results`, `general/blog`, `singular/post_type`, `singular/by_author`, `singular/specific_post`, `singular/child_of`, `archive/post_type`, `archive/all`, `archive/date`, `archive/author`, plus `singular/in_tax/{taxonomy}` and `archive/tax/{taxonomy}` for registered taxonomies. Preserve the full path when a predicate moves or is unavailable; do not replace an unknown condition with an empty list.

## Existing HTTP endpoints

All routes below currently use the `elemacy` REST namespace. The normal pretty-permalink base is `/wp-json/elemacy`; clients must use WordPress's generated REST URL so plain permalinks and subdirectory installs work. Braced parameters represent the existing registered parameter pattern, not a new URL version.

| Source config | Methods and relative paths | Existing policy |
| --- | --- | --- |
| `F/src/Config/api.php` | `GET /modules`; `PUT /modules/{name}` | Admin middleware |
| Same | `GET /conditions/types`; `GET /conditions/search` | Admin middleware |
| Same | `GET /library/template-types`; `GET, POST /library/templates`; `GET, PUT, DELETE /library/templates/{id}`; `POST /library/templates/{id}/duplicate` | Admin middleware |
| `F/src/Modules/Widgets/Config/api.php` | `GET /widgets`; `PUT /widgets/{name}/toggle` | Admin middleware |
| `F/src/Modules/ThemeBuilder/Config/api.php` | `GET /theme-builder/template-types`; `GET, POST /theme-builder/templates`; `GET, PUT, DELETE /theme-builder/templates/{id}`; `POST /theme-builder/templates/{id}/duplicate` | Admin middleware |
| `F/src/Modules/Popups/Config/api.php` | `GET /popups/triggers`; `GET /popups/rules`; `GET, POST /popups`; `GET, PUT, DELETE /popups/{id}`; `POST /popups/{id}/duplicate` | Admin middleware |
| `P/src/Core/License/Config/api.php` | `GET /license`; `POST /license/activate`; `POST /license/deactivate` | Auth and admin middleware |
| `P/src/Extensions/Popups/Config/api.php` | `GET /popups/{id}/analytics` | Admin middleware |
| Same | `POST /popups/{id}/track` | Public tracking middleware |

Frontend `admin-ajax.php` actions from `Modules/Widgets/Config/ajax.php`:

| Action | Nonce action |
| --- | --- |
| `elemacy_widgets_loop_grid_pagination` | `elemacy_ajax_nonce` |
| `elemacy_widgets_form_submit` | `elemacy_widgets_form_submit` |

These are currently registered for authenticated and guest requests. The public nonce does not establish resource authorization. Preserve action names and the successful loop response keys `items`/`pagination`; reject the old settings-authoritative request protocol explicitly under phase 1.

## Shared PHP and admin entry points

Preserve the supported `Core/Container::{instance,make,singleton}` surface while adding explicit bindings (done: task 6.1). Keep the existing `LoopItemInterface`, `LoopDataSourceInterface`, `LoopContext`, `LoopDataSourceRegistry` and `LoopItemStyles` paths usable through compatibility entry points when moving their implementations; old/new paths must share state (done: tasks 7.1–7.2 — `LoopDataSourceInterface` itself was narrowed via a descriptor rather than moved, task 7.3; see "Phase 7 update" below for the full move map and the mechanism that keeps old/new paths sharing state).

Free owns the admin mount `#elemacy_root` and the `window.ElemacyShared` runtime/slot bridge. Pro consumes it. Popup bootstrap globals include `window.elemacyPopups` and Pro's `window.elemacyPopupsAnalytics`. Version/extend these surfaces deliberately; namespace cleanup alone is not a reason to rename them.

The Pro activation/grace/outage policy in `P/CONTRIBUTING.md` and `Core/License/LicenseManager.php` is preserved: formerly valid licenses have the existing 14-day grace, never-valid installations are immediately gated, transport failures retain cached state, and Pro conditions are registered independently of the gated modules/extensions.

## Public hook inventory

Every existing `Core/Hooks.php` constant and literal is listed below. Added hooks must use this same central owner; do not substitute a raw string in a consumer.

| Constant | Hook value |
| --- | --- |
| `LOADED_ACTION` | `elemacy/action/loaded` |
| `REGISTER_MODULES_ACTION` | `elemacy/action/register_modules` |
| `VERSION_UPDATED_ACTION` | `elemacy/action/version_updated` |
| `LIBRARY_TYPES_REGISTER_ACTION` | `elemacy/action/library/types/register` |
| `TEMPLATE_ASSETS_COLLECT_ACTION` | `elemacy/action/template_assets/collect` |
| `ADMIN_SCRIPT_DATA_FILTER` | `elemacy/filter/admin_script_data` |
| `ENQUEUE_ADMIN_SCRIPTS_ACTION` | `elemacy/action/enqueue_admin_scripts` |
| `FRONTEND_SCRIPT_DATA_FILTER` | `elemacy/filter/frontend_script_data` |
| `WIDGETS_REGISTER_FILTER` | `elemacy/filter/widgets/register` |
| `THEME_BUILDER_TEMPLATE_TYPES_FILTER` | `elemacy/filter/theme_builder/template_types` |
| `THEME_BUILDER_FALLBACK_HEADER_ACTION` | `elemacy/action/theme_builder/fallback_header` |
| `THEME_BUILDER_LOCATIONS_REGISTER_ACTION` | `elemacy/action/theme_builder/locations/register` |
| `LOOP_DATA_SOURCES_REGISTER_ACTION` | `elemacy/action/loop/data_sources/register` |
| `PREVIEW_QUERY_ARGS_FILTER` | `elemacy/filter/preview/query_args` |
| `CONDITIONS_EXCLUDED_POST_TYPES_FILTER` | `elemacy/filter/conditions/excluded_post_types` |
| `CONDITIONS_EXCLUDED_TAXONOMIES_FILTER` | `elemacy/filter/conditions/excluded_taxonomies` |
| `CONDITIONS_REGISTER_ACTION` | `elemacy/action/conditions/register` |
| `POPUP_DISPLAY_SETTINGS_FILTER` | `elemacy/filter/popups/display_settings` |
| `POPUP_FRONTEND_CONFIG_FILTER` | `elemacy/filter/popups/frontend_config` |
| `POPUP_MATCHED_FILTER` | `elemacy/filter/popups/matched` |
| `POPUP_DUPLICATE_EXCLUDED_META_FILTER` | `elemacy/filter/popups/duplicate_excluded_meta` |
| `POPUP_TRIGGERS_REGISTER_ACTION` | `elemacy/action/popups/triggers/register` |
| `POPUP_RULES_REGISTER_ACTION` | `elemacy/action/popups/rules/register` |

Verification: checked the tables against the referenced Config/Constants files using `rg`; the hook table was extracted from `Core/Hooks.php` at the recorded revision. Future behavioral tests belong to their respective implementation phases.

## Phase 7 update: canonical `Loop`/`Integrations/Acf` move map and dependency boundaries

Phase 7 (tasks 7.1–7.11) moved the classes line 128 above named as "compatibility entry points when moving their implementations" into their new canonical homes. Every old path below is a real `class_alias()` identity (or, where the calling convention itself changed from static to instance, a thin static facade) at the *first supported Free/Pro compatibility window* — not a copy, and old/new paths share the exact same state. New code should import the canonical path; existing code at the old path (including Pro's own, and this repository's own not-yet-implemented `acf-flexible-content-widget` proposal) needs no changes to keep working.

| Old path (`Elemacy\Modules\Widgets\...` unless noted) | Canonical path (`Elemacy\...`) | Moved in |
| --- | --- | --- |
| `Contracts\LoopItemInterface` | `Loop\Contracts\LoopItemInterface` | 7.1 |
| `DTO\LoopResultDTO` | `Loop\DTO\LoopResultDTO` | 7.1 |
| `Services\LoopContext` | `Loop\LoopContext` | 7.1 |
| `Services\LoopDataSourceRegistry` | `Loop\LoopDataSourceRegistry` | 7.2 |
| `Services\LoopItemStyles` | `Loop\LoopItemStyles` | 7.2 |
| `Support\LoopItemIdentity` | `Loop\Support\LoopItemIdentity` | 7.2 |
| `LoopItems\{Post,Term,User}LoopItem` | `Loop\LoopItems\{Post,Term,User}LoopItem` | 7.2 |
| `Contracts\PublicLoopSourcePolicyInterface` | `Loop\Contracts\PublicLoopSourcePolicyInterface` | 7.3 |
| `DTO\PublicLoopRequest` | `Loop\Pagination\DTO\PublicLoopRequest` | 7.4 |
| `DTO\PublishedLoopDefinition` | `Loop\Pagination\DTO\PublishedLoopDefinition` | 7.4 |
| `Services\PublicRenderPolicy` | `Loop\Pagination\PublicRenderPolicy` | 7.4 |
| `Services\PublishedLoopDefinitionResolver` | `Loop\Pagination\PublishedLoopDefinitionResolver` | 7.4 |
| `Services\PaginationContextSigner` | `Loop\Pagination\PaginationContextSigner` | 7.4 |
| `Elemacy\Modules\DynamicTags\Services\Acf` (static facade kept — see below) | `Elemacy\Integrations\Acf\AcfFieldReader` (instance) | 7.5 |
| `Services\AcfRepeaterResolver` | `Elemacy\Integrations\Acf\AcfRepeaterResolver` | 7.5 |

Not moved by any task in this phase — referenced from their existing path by every canonical `Loop/` file above that needs them, and allowlisted explicitly in `tests/architecture/dependency-boundaries.php`: `Contracts\LoopDataSourceInterface` (narrowed via `Loop\SourceDefinition`, task 7.3, but the aggregate interface itself stays; migrating each built-in source onto the narrow contracts directly is not in this phase's scope), `Services\PlacementResolverRegistry`, `Support\PublicLoopLimits`. Pro's own `Extensions/LoopDataSources/{ProLoopDataSources,AcfRepeaterDataSource,AcfRepeaterRowLoopItem}.php` import the canonical paths above directly (task 7.8); Pro additionally gained its own internal split, entirely within its own namespace: `AcfRepeaterDataSource`'s runtime moved to **new** `ElemacyPro\Extensions\LoopDataSources\Query\AcfRepeaterQuery` and `ElemacyPro\Extensions\LoopDataSources\Policies\AcfRepeaterPolicy` (task 7.11).

`Elemacy\Modules\DynamicTags\Services\Acf`'s move is the one exception to the pure-alias pattern: its methods were static, and the canonical `AcfFieldReader` is an instance (constructor-injectable, per design.md's "loop items consume an optional field-reader contract" — see **new** `Integrations\Acf\Contracts\FieldReaderInterface` and `Integrations\Acf\UnavailableFieldReader`, the safe no-op implementation). The old `Acf` class is kept as a thin static facade delegating to a fresh `AcfFieldReader`, so every existing static `Acf::get_field_value()` call site — Dynamic Tags' own ACF tags included — needed no changes.

**New shared capability contracts**, not moves, task 7.7: `Loop\Contracts\{NativeTermItemInterface,NativeUserItemInterface}` — optional capabilities `TermLoopItem`/`UserLoopItem` implement so `Modules\DynamicTags\Services\{Taxonomy,User}` can recognize a native term/user-backed loop item via `instanceof` without depending on either concrete class. The base `LoopItemInterface` itself was deliberately not widened with `get_term()`/`get_user()`.

**New preview capability contract**, task 7.6: `Core\Contracts\PreviewContextProviderInterface`, implemented by **new** `Loop\Preview\LoopItemPreviewContextProvider` — `Core\Preview\PreviewManager` consumes only the contract, never `Loop\LoopContext` directly.

### Dependency boundary enforcement

`tests/architecture/dependency-boundaries.php` (run in CI alongside the existing unit-test step) scans this plugin's own source tree for three forbidden import directions and fails the build on any unlisted match: `Core → Modules\Widgets`, `Modules\Widgets → Modules\DynamicTags`, `Modules\DynamicTags → Modules\Widgets`, and (new, since `Loop/` itself must not silently regain the same coupling it was extracted to remove) `Loop → Modules\Widgets`. Its own explicit allowlist is the authoritative, single place every accepted exception above is recorded with the reason it exists — a new violation anywhere else in the tree fails the build; a *new* violation in an already-listed file (an import the allowlist does not name) fails it too, since the allowlist matches exact `use` lines, not whole files.

### `acf-flexible-content-widget`'s own forwarding-path compatibility

The separate, not-yet-implemented `acf-flexible-content-widget` proposal (`openspec/changes/acf-flexible-content-widget/`) documents itself as reusing `LoopItemInterface`, `LoopContext` and `LoopItemStyles` "unmodified" from the old `Modules\Widgets` paths. Every one of those is now a real alias of its `Loop/` canonical counterpart (tasks 7.1–7.2), so that proposal's own design remains buildable exactly as written, with no changes needed to it from this change. Reconciling its own references to the canonical paths — or removing the aliases entirely — is explicitly deferred to a separate, authorized change that touches that proposal directly, per design.md D7's own instruction; this change does not modify `acf-flexible-content-widget/`'s own files.

### Deprecation window

Every alias/facade this phase left behind is intended to remain for "the first supported Free/Pro compatibility window" (design.md D7) — i.e., until a future, separately-authorized change either updates every remaining old-path consumer (in this repository and Pro's) to the canonical path and then removes the alias, or makes an explicit, documented decision to keep it indefinitely. No removal is scheduled or implied by this change; `tests/architecture/dependency-boundaries.php`'s allowlist is the concrete, current inventory of what a future cleanup would need to revisit.

## Phase 8 update: ACF's internal row-loop stack (`AcfRowScope`)

`Integrations/Acf/AcfRowScope.php` (task 8.3) reaches into one internal — but public-property, non-reflection — ACF surface with no official save/restore equivalent: `acf()->loop` (an `acf_loop` instance, ACF core's own global singleton accessor) and its public `$loops` array, a stack of currently-active `have_rows()`/`the_row()` loops.

**Verified against source, this environment's installed version**: `advance-custom-fields-pro/includes/loop.php` defines `class acf_loop` with a public `$loops` array and `add_loop()`/`update_loop()`/`get_loop()`/`remove_loop()` methods, aliased by the global `acf_add_loop()`/`acf_update_loop()`/`acf_get_loop()`/`acf_remove_loop()` functions. `includes/api/api-template.php`'s `have_rows($selector, $post_id)` compares `"selector={$selector}/post_id={$post_id}"` only against the array's *own* top ("active") entry: an unchanged key continues that same entry in place; a changed key either pushes a new nested "child" entry (when the new selector resolves as a sub-field of the active row — ACF's own supported nested-repeater mechanism) or a new sibling "parent" entry, and an exhausted entry is popped automatically. `acf_loop`'s own docblocks date this shape to ACF 5.3.2 — a longstanding, not version-fragile, surface, but still undocumented in ACF's public field-API reference and therefore treated as internal: isolated in this one adapter class rather than referenced from `AcfRepeaterRowLoopItem.php` or anywhere else directly, per design.md D8's own instruction ("use supported APIs where available, otherwise encapsulate version-verified internal snapshot/restore in this one adapter").

**What `AcfRowScope` relies on, precisely**: that `acf()->loop->loops` exists, is a plain array, and that replacing it wholesale with a previously-captured copy is equivalent to "nothing happened" from `have_rows()`/`the_row()`/`get_sub_field()`'s own point of view (true today, since those functions only ever read/write this same array via the accessor functions above — confirmed by reading their full implementation, not inferred from behavior alone). A future ACF release changing `acf_loop`'s internal shape, or moving loop state off `acf()->loop` entirely, would need this class re-verified; nothing outside `AcfRowScope` needs to change if so, by construction.

**Known, accepted gap, not fixed by task 8.3**: `AcfRepeaterQuery::get_items()`'s own eager `have_rows()`/`the_row()` pre-walk (building the list of `AcfRepeaterRowLoopItem`s for a widget to render) still shares the live cursor with whatever `have_rows()` loop happens to be active when it runs — a nested/repeated `get_items()` call for the same field+post_id mid-iteration sees fewer rows than a fresh query would, and its returned items' `row_index` values are only meaningful relative to a fresh walk. `AcfRowScope::snapshot()`/`restore()` are exposed as static methods specifically so a future task naming `AcfRepeaterQuery.php` can wrap its own pre-walk the same way, without duplicating this class's internal-API knowledge.
