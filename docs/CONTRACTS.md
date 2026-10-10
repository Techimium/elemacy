# Contracts: identifiers that never change

Stored identifiers, saved names, HTTP paths and hook names that saved sites, Pro and third-party code depend on. A code move never renames anything here; a rename is a migration with its own change.

`F/` is the Elemacy plugin root; `P/` is the Elemacy Pro root. Paths without a prefix are relative to F.

## Permanent identifiers

These are written into sites' databases and saved Elementor data. Renaming one silently breaks every site that stored the old value, so a rename is never a refactor: it ships a `Core\Migrator` step, in the same change, that rewrites the stored value.

- **Module slugs**: `theme-builder`, `widgets`, `custom-css`, `dynamic-tags`, `popups`, stored in `elemacy_active_modules`.
- **Template type names**: every stored `_elemacy_template_type` value in § Library types and Elementor document names.
- **Elementor document type names**: every stored `_elementor_template_type` value in the same table.
- **Stored meta and option keys**: every key in § Storage ownership.
- **Elementor widget names and loop data-source keys**: every value in § Saved module, widget and data-source names, saved as `widgetType` and `data_source` in `_elementor_data`.
- **Dynamic tag names**: every value in § Dynamic tag names, saved inside `[elementor-tag name="..."]` references.

`tests/architecture/check.php` enforces this. `tests/architecture/persisted-identifiers.php` lists each identifier with the source file that declares it, and the check fails when an identifier is no longer declared there unless a migration under `src/Core/Migrations/` still names it.

### Released defaults are frozen too

Elementor saves document, element and dynamic tag settings with `toJSON({remove: ["default"]})`: a setting left at its control's default is not stored at all. Changing a released control's `default` therefore silently changes every saved site that never touched that control. Add a new control (or a new option value) instead of changing a default.

The same holds for a module's `Module::is_default_active()`: `elemacy_active_modules` stores only explicit choices, so flipping a released module's declared default changes every site that never toggled it.

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
| `elemacy_active_modules` | `src/Core/Constants/OptionKeys.php`; `slug => bool` map of explicit administrator choices only; an absent module follows its declared default, and a stored list of slugs (pre-release shape) reads as that active set |
| `elemacy_db_version` | Same owner; currently a release-version string, despite its schema-oriented name |
| `elemacy_schema_version` | `src/Core/Constants/OptionKeys.php`; completed Free data migrations, advanced only by `Core\Migrator` |
| `elemacy_library_index` | `src/TemplateLibrary/ResolverCache.php`; rebuildable published candidate index, **non-autoloaded in code**, stored as `{shape, index}`; any `shape` other than `ResolverCache::SHAPE` is a miss and is rebuilt |
| `elemacy_widgets` | `src/Modules/Widgets/Services/WidgetService.php`; widget status map, built through `Utils::with_prefix('widgets')` |
| `elemacy_pro_license_key` | Pro `Core/License/LicenseManager.php`; stored license key; never include its value in fixtures/logs |
| `elemacy_pro_license_status` | Same owner; `{valid, expires, plan, grace_start}` with defaults supplied on read |
| `elemacy_form_limit_*` | Free form submission rate-limit rows (options, non-autoloaded, HMAC of the IP) |
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

## Dynamic tag names

Every built-in tag is registered under a name starting with `elemacy-`, because Elementor's tag registry is keyed by name alone and an unprefixed name can replace, or be replaced by, another plugin's tag (Elementor Pro ships `post-url` and `post-excerpt`). `tests/architecture/check.php` rejects a built-in tag whose `get_name()` lacks the prefix. The catalog authority is `src/Modules/DynamicTags/Config/tags.php`; the `elemacy-woocommerce` group registers only while WooCommerce is active.

| Tag group | Tag class under `Modules/DynamicTags/Tags` | Stored tag name |
| --- | --- | --- |
| `elemacy` | `Base/PostTitle` | `elemacy-post-title` |
| `elemacy` | `Base/PostContent` | `elemacy-post-content` |
| `elemacy` | `Base/PostExcerpt` | `elemacy-post-excerpt` |
| `elemacy` | `Base/PostDate` | `elemacy-post-date` |
| `elemacy` | `Base/PostCustomField` | `elemacy-post-custom-field` |
| `elemacy` | `Base/PostUrl` | `elemacy-post-url` |
| `elemacy` | `Base/PostFeaturedImage` | `elemacy-post-featured-image` |
| `elemacy` | `Base/PostTerms` | `elemacy-post-terms` |
| `elemacy` | `Base/CommentsNumber` | `elemacy-comments-number` |
| `elemacy` | `Base/PageTitle` | `elemacy-page-title` |
| `elemacy` | `Base/SiteLogo` | `elemacy-site-logo` |
| `elemacy` | `Base/SiteTagline` | `elemacy-site-tagline` |
| `elemacy` | `Base/SiteTitle` | `elemacy-site-title` |
| `elemacy` | `Base/SiteUrl` | `elemacy-site-url` |
| `elemacy` | `Archive/ArchiveTitle` | `elemacy-archive-title` |
| `elemacy` | `Archive/ArchiveDescription` | `elemacy-archive-description` |
| `elemacy` | `Archive/ArchiveUrl` | `elemacy-archive-url` |
| `elemacy` | `Taxonomy/TermName` | `elemacy-term-name` |
| `elemacy` | `Taxonomy/TermDescription` | `elemacy-term-description` |
| `elemacy` | `Taxonomy/TermUrl` | `elemacy-term-url` |
| `elemacy` | `Taxonomy/TermCount` | `elemacy-term-count` |
| `elemacy` | `Taxonomy/TermCustomField` | `elemacy-term-custom-field` |
| `elemacy` | `Author/AuthorName` | `elemacy-author-name` |
| `elemacy` | `Author/AuthorInfo` | `elemacy-author-info` |
| `elemacy` | `Author/AuthorUrl` | `elemacy-author-url` |
| `elemacy` | `Author/AuthorProfilePicture` | `elemacy-author-profile-picture` |
| `elemacy` | `User/UserName` | `elemacy-user-name` |
| `elemacy` | `User/UserDescription` | `elemacy-user-description` |
| `elemacy` | `User/UserAvatar` | `elemacy-user-avatar` |
| `elemacy` | `User/UserUrl` | `elemacy-user-url` |
| `elemacy` | `User/UserLogin` | `elemacy-user-login` |
| `elemacy` | `User/UserEmail` | `elemacy-user-email` |
| `elemacy` | `User/UserPostCount` | `elemacy-user-post-count` |
| `elemacy` | `Utility/Shortcode` | `elemacy-shortcode` |
| `elemacy` | `Utility/RequestParameter` | `elemacy-request-parameter` |
| `elemacy` | `Utility/CurrentDateTime` | `elemacy-current-date-time` |
| `elemacy` | `Utility/ContactUrl` | `elemacy-contact-url` |
| `elemacy` | `Acf/AcfField` | `elemacy-acf-field` |
| `elemacy` | `Acf/AcfColor` | `elemacy-acf-color` |
| `elemacy` | `Acf/AcfDateTime` | `elemacy-acf-date-time` |
| `elemacy` | `Acf/AcfGallery` | `elemacy-acf-gallery` |
| `elemacy` | `Acf/AcfImage` | `elemacy-acf-image` |
| `elemacy` | `Acf/AcfNumber` | `elemacy-acf-number` |
| `elemacy` | `Acf/AcfUrl` | `elemacy-acf-url` |
| `elemacy-woocommerce` | `WooCommerce/ProductPrice` | `elemacy-product-price` |
| `elemacy-woocommerce` | `WooCommerce/ProductSku` | `elemacy-product-sku` |
| `elemacy-woocommerce` | `WooCommerce/ProductStock` | `elemacy-product-stock` |
| `elemacy-woocommerce` | `WooCommerce/ProductRating` | `elemacy-product-rating` |
| `elemacy-woocommerce` | `WooCommerce/ProductShortDescription` | `elemacy-product-short-description` |
| `elemacy-woocommerce` | `WooCommerce/ProductGallery` | `elemacy-product-gallery` |
| `elemacy-woocommerce` | `WooCommerce/ProductAddToCartUrl` | `elemacy-product-add-to-cart-url` |

## Library types and Elementor document names

| Library group | Stored library type | Elementor document type |
| --- | --- | --- |
| `block` | `section` | `wp-page` |
| `block` | `loop` | `elemacy_loop` |
| `theme` | `header` | `elemacy_header` |
| `theme` | `footer` | `elemacy_footer` |
| `theme` | `404` | `wp-page` fallback |
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

Pro's license policy (`P/CONTRIBUTING.md`, `Core/License/LicenseManager.php`) is a contract: a formerly valid license keeps a 14-day grace window, a never-valid installation is gated immediately, transport failures keep the cached state, and every Pro capability — conditions included — registers only while the license is active (see `P/DEVELOPER.md`).

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
| `THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER` | `elemacy/filter/theme_builder/suppress_native_chrome` |
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
| `DYNAMIC_TAGS_REGISTER_ACTION` | `elemacy/action/dynamic_tags/register` |

### Opting a theme out of native chrome replacement

On a classic theme without a dedicated adapter, a configured Header or Footer template replaces the theme's own `header.php` or `footer.php`: the theme's file is loaded and its output discarded (see `docs/ARCHITECTURE.md` § Native chrome replacement). Markup the theme opens in `header.php` is discarded with it: on themes that open their layout wrappers there and close them in `footer.php` (most `_s`-based themes, Twenty Twenty-One among them), replacing only one side leaves those wrappers unbalanced, and a theme whose content template closes markup its `header.php` opened can break outright. `THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER` is the escape hatch. It is applied once per side per request, with `true` and the side (`'header'` or `'footer'`). Returning `false` leaves that side to the theme: the theme's own chrome renders and the Elemacy template for that side does not. The other side is unaffected.

```php
add_filter('elemacy/filter/theme_builder/suppress_native_chrome', function (bool $suppress, string $side): bool {
    return 'header' === $side ? false : $suppress;
}, 10, 2);
```
