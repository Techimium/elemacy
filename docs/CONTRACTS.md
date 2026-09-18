# Contracts: identifiers that never change

Stored identifiers, saved names, HTTP paths and hook names that saved sites, Pro and third-party code depend on. A code move never renames anything here; a rename is a migration with its own change.

`F/` is the Elemacy plugin root; `P/` is the Elemacy Pro root. Paths without a prefix are relative to F.

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

Elementor authoring metadata is also a compatibility boundary: `_elementor_data`, `_elementor_edit_mode`, `_elementor_template_type`, `_elementor_page_settings`, plus Elementor-owned generated asset metadata such as `_elementor_css`. Native `_wp_page_template` also participates in document behavior. `DocumentDuplicator` copies authoring values only, never Elementor's generated runtime state or Pro's counters.

## Saved module, widget and data-source names

Enabled Free module slugs: `theme-builder`, `widgets`, `custom-css`, `dynamic-tags`, `popups`. Source: `src/Config/modules.php` and each module's `get_name()`.

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

The same registration contributes query controls and standalone preview. `template_id`, the Elementor element `id`, source query settings and `preview_data_source` are saved document values; public pagination requests never carry them. Loop Carousel has no AJAX pagination feature.

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

Each type's Elementor document class is declared on its `TypeDefinition`; `TemplateLibrary\DocumentWriter` writes `_elementor_template_type` from it, and every popup type resolves to the single `elemacy_popup` document. Theme content-location order is `404` (5), `single` (10), `search` (15), `archive` (20); header and footer are separate placements.

## Popup targeting and display settings

Trigger identifiers: `page_load`, `click`, `scroll`, `exit_intent`, `inactivity`, `scroll_to_element`, `on_popup_close`.

Rule identifiers: `frequency_cap`, `logged_in`, `once_per`, `devices`, `schedule`, `page_views`, `sessions`, `referrer`, `roles`, `browser`.

Free supplies real page-load/click triggers and frequency/logged-in rules; Pro supplies implementations for the corresponding Free mocks. Source registries and `get_name()` values, rather than translated labels, identify stored configuration. Rule/trigger `params` retain their type-owned shapes. Schedule datetime values currently use timezone-naive `YYYY-MM-DDTHH:mm` strings.

The following `DisplayKeys` values are stored in Elementor page settings and consumed by `DocumentDisplay`. Their authority is `src/Modules/Popups/Support/DisplayKeys.php`; defaults are in `DisplayDefaults.php`:

`elemacy_position`, `elemacy_sticky`, `elemacy_width`, `elemacy_z_index`, `elemacy_offset_x`, `elemacy_offset_y`, `elemacy_overlay_color`, `elemacy_overlay_opacity`, `elemacy_overlay_blur`, `elemacy_animation_in`, `elemacy_animation_out`, `elemacy_close_button`, `elemacy_auto_close`, `elemacy_close_on_overlay`, `elemacy_close_on_esc`, `elemacy_prevent_scroll`.

Responsive variants and legacy display settings must continue following `DocumentDisplay` and the existing popup positioning spec. `elemacy_animation_in/out` here belong to native popup transitions, not the excluded Animations module.

Condition identifiers come from `src/Conditions/*/get_name()` and Pro's matching implementations: `general/entire_site`, `general/front_page`, `general/not_found`, `general/search_results`, `general/blog`, `singular/post_type`, `singular/by_author`, `singular/specific_post`, `singular/child_of`, `archive/post_type`, `archive/all`, `archive/date`, `archive/author`, plus `singular/in_tax/{taxonomy}` and `archive/tax/{taxonomy}` for registered taxonomies. Preserve the full path when a predicate moves or is unavailable; do not replace an unknown condition with an empty list.

## HTTP endpoints

All routes below currently use the `elemacy` REST namespace. The normal pretty-permalink base is `/wp-json/elemacy`; clients must use WordPress's generated REST URL so plain permalinks and subdirectory installs work. Braced parameters represent the existing registered parameter pattern, not a new URL version.

| Source config | Methods and relative paths | Access policy |
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

Both actions serve guests and authenticated users. The public nonce is request hygiene, not authorization; each handler applies its own application policy. The successful loop response keys are `items` and `pagination`; the loop request is the signed envelope described in `docs/ARCHITECTURE.md`.

## Shared PHP and admin entry points

`Core\Container::{instance,make,singleton,bind}` is the composition surface. The loop layer's canonical classes live under `Elemacy\Loop` (`Contracts\LoopDataSourceInterface`, `Contracts\LoopItemInterface`, `LoopContext`, `LoopDataSourceRegistry`, `Rendering\LoopItemStyles`, `Placement\PlacementResolverRegistry`, `Pagination\*`); ACF access under `Elemacy\Integrations\Acf`; the HTTP layer under `Elemacy\Http`; everything that calls Elementor under `Elemacy\Elementor`. There are no alias paths.

Free owns the admin mount `#elemacy_root` and the `window.ElemacyShared` bridge (contract version `AdminScripts::CONTRACT_VERSION`, the `elemacy:admin-ready` event). Popup bootstrap globals are `window.elemacyPopups` and Pro's `window.elemacyPopupsAnalytics`. Version these surfaces deliberately; never rename them for tidiness.

Pro's license policy (`P/CONTRIBUTING.md`, `Core/License/LicenseManager.php`) is a contract: a formerly valid license keeps a 14-day grace window, a never-valid installation is gated immediately, transport failures keep the cached state, and Pro conditions register regardless of entitlement.

## Public hook inventory

Every `Core\Hooks` constant. New hooks are added there; a consumer never uses a raw string.

| Constant | Hook value |
| --- | --- |
| `LOADED_ACTION` | `elemacy/action/loaded` |
| `REGISTER_MODULES_ACTION` | `elemacy/action/register_modules` |
| `REGISTER_PROVIDERS_ACTION` | `elemacy/action/register_providers` |
| `VERSION_UPDATED_ACTION` | `elemacy/action/version_updated` |
| `LIBRARY_DUPLICATE_EXCLUDED_META_FILTER` | `elemacy/filter/library/duplicate_excluded_meta` |
| `CACHE_PURGE_ACTION` | `elemacy/action/cache/purge` |
| `LIBRARY_CHANGED_ACTION` | `elemacy/action/library/changed` |
| `LIBRARY_TYPES_REGISTER_ACTION` | `elemacy/action/library/types/register` |
| `TEMPLATE_ASSETS_COLLECT_ACTION` | `elemacy/action/template_assets/collect` |
| `ADMIN_SCRIPT_DATA_FILTER` | `elemacy/filter/admin_script_data` |
| `ENQUEUE_ADMIN_SCRIPTS_ACTION` | `elemacy/action/enqueue_admin_scripts` |
| `FRONTEND_SCRIPT_DATA_FILTER` | `elemacy/filter/frontend_script_data` |
| `WIDGETS_REGISTER_FILTER` | `elemacy/filter/widgets/register` |
| `THEME_BUILDER_TEMPLATE_TYPES_FILTER` | `elemacy/filter/theme_builder/template_types` |
| `THEME_BUILDER_FALLBACK_HEADER_ACTION` | `elemacy/action/theme_builder/fallback_header` |
| `THEME_BUILDER_LOCATIONS_REGISTER_ACTION` | `elemacy/action/theme_builder/locations/register` |
| `THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION` | `elemacy/action/theme_builder/compatibility/register` |
| `LOOP_DATA_SOURCES_REGISTER_ACTION` | `elemacy/action/loop/data_sources/register` |
| `LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION` | `elemacy/action/loop/placement_resolvers/register` |
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
