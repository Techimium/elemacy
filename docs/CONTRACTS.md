# Contracts: identifiers that never change

Stored identifiers, saved names, HTTP paths and hook names that saved sites, Pro and third-party code depend on. A code move never renames anything here; a rename is a migration with its own change.

`F/` is the Elemacy plugin root; `P/` is the Elemacy Pro root. Paths without a prefix are relative to F.

## Permanent identifiers

These are written into sites' databases and saved Elementor data. Renaming one silently breaks every site that stored the old value, so from the 1.0 release on a rename is never a refactor: it ships a `Core\Migrator` step, in the same change, that rewrites the stored value.

- **Module slugs**: `theme-builder`, `widgets`, `custom-css`, `dynamic-tags`, `popups`, stored in `elemacy_active_modules`.
- **Template type names**: every stored `_elemacy_template_type` value in § Library types and Elementor document names.
- **Elementor document type names**: every stored `_elementor_template_type` value in the same table.
- **Stored meta and option keys**: every key in § Storage ownership.
- **Elementor widget names and loop data-source keys**: every value in § Saved module, widget and data-source names, saved as `widgetType` and `data_source` in `_elementor_data`.
- **Dynamic tag names**: every value in § Dynamic tag names, saved inside `[elementor-tag name="..."]` references.
- **Popup trigger and rule identifiers and their parameter keys**, the **popup document setting names** (`DisplayKeys` and the close button style controls), the **position and animation option values**, and the prefix of the browser storage key each popup uses (`elemacy_popup_{id}`): § Popup targeting and display settings. Pro's visit-counter storage keys (`elemacy_pageviews`, `elemacy_sessions`, `elemacy_session_counted`) are pinned by `P/tests/php/run.php`, which also fails when a Pro trigger or rule differs from its Free placeholder in id or parameter keys.

`tests/architecture/check.php` enforces this. `tests/architecture/persisted-identifiers.php` lists each identifier with the source file that declares it, and the check fails when an identifier is no longer declared there unless a migration under `src/Core/Migrations/` still names it.

### Released defaults are frozen too

Elementor saves document, element and dynamic tag settings with `toJSON({remove: ["default"]})`: a setting left at its control's default is not stored at all. Changing a released control's `default` therefore silently changes every saved site that never touched that control. Add a new control (or a new option value) instead of changing a default.

**Popup control defaults are pinned by a test.** `tests/integration/fixtures/popup-control-defaults.json` holds the default of every `elemacy_*` control the popup document registers, for each of the four popup types, and `popup-display-defaults.php` fails with the control, the type and both values when one changes, when a new control has no frozen default, or when a frozen control disappears. A deliberate change ships a migration that writes the old default into saved popups, and then regenerates the fixture (the command is in that test's docblock) with the diff reviewed by hand.

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
| `elemacy_pro_license_status` | Same owner; `{valid, expires, plan}` with defaults supplied on read |
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

The Nav Menu's saved setting names are the Elementor Pro Nav Menu's control IDs (`menu`, `layout`, `pointer`, `animation_line`, `dropdown`, `toggle` and the rest, checked against `tests/integration/fixtures/nav-menu-reference-controls.json`), so Paste Style works between the two widgets. Keep them. Everything the widget puts on the page is Elemacy's own and is not a public styling API: classes under `elemacy-nav-menu`, the custom properties `--elemacy-nav-menu-*`, and the script and style handle `elemacy-nav-menu`.

| Data-source implementation | Saved `data_source` value |
| --- | --- |
| Free `PostsDataSource` | `posts` |
| Free `CurrentQueryDataSource` | `current_query` |
| Free `TaxonomyDataSource` | `terms` |
| Free `UsersDataSource` | `users` |
| Pro `AcfRepeaterDataSource` | `acf_repeater` |

The same registration contributes query controls and standalone preview. `template_id`, the Elementor element `id`, source query settings and `preview_data_source` are saved document values; public pagination requests never carry them. Loop Carousel has no AJAX pagination feature. A source that also implements `Loop\Contracts\PageUrlPaginatedSourceInterface` (Current Query) is paginated by the host page's own URLs instead of the endpoint, which refuses it.

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
| `theme` | `404` | `elemacy_404` |
| `theme` | `single` | `elemacy_single` |
| `theme` | `archive` | `elemacy_archive` |
| `theme` | `search` | `elemacy_search` |
| `popup` | `popup`, `topbar`, `banner`, `floating` | `elemacy_popup` |

Each type's Elementor document class is declared on its `TypeDefinition`; `TemplateLibrary\DocumentWriter` writes `_elementor_template_type` from it, and every popup type resolves to the single `elemacy_popup` document. Every Theme Builder type has a document of its own. Theme content-location order is `404` (5), `single` (10), `search` (15), `archive` (20); header and footer are separate placements. Add-ons add a Theme Builder type by registering a `TypeDefinition` in the `theme` group (on `LIBRARY_TYPES_REGISTER_ACTION`); that registry is what the type picker lists and what saving validates against.

### Theme template targeting

A `TypeDefinition`'s `targeting` decides how its single winning template is chosen, and is part of the contract:

- `conditions` (Header, Footer, Single, Archive, Search, and the default for an add-on type): a published template displays only where one of its inclusion rules matches and none of its exclusion rules does. A template with no inclusion rule — none at all, or only exclusions — displays nowhere; "everywhere" is the explicit broadest condition (`general/entire_site`, `singular/post_type` or `archive/post_type` with no value, `search/all`), which the type's `default_conditions` pre-fill for a new template. A rule naming an unavailable condition suspends the template.
- `location` (404): the type offers no conditions, and the newest published template covers every request of its location.

Among matching templates the most specific wins; within one level of specificity, the newest by publish date, then the higher post id. That tie-break is stable. Popups are collect-all and keep their own semantics: a popup with no conditions displays everywhere.

The ChromeDocument setting `elemacy_html_tag` (`div`, `header`, `footer`, `main`, `section`, `aside`, `nav`, `article`; default `header`/`footer`) names the element a Header or Footer renders in. Where a theme adapter declares the theme keeps its own landmark around the template (`ThemeBuilderManager::keep_theme_landmark()`), a `header`/`footer` choice renders as `div` so landmarks never nest.

## Popup targeting and display settings

Trigger identifiers: `page_load`, `click`, `scroll`, `exit_intent`, `inactivity`, `scroll_to_element`, `on_popup_close`.

Rule identifiers: `frequency_cap`, `logged_in`, `once_per`, `devices`, `schedule`, `page_views`, `sessions`, `referrer`, `roles`, `browser`.

Free supplies real page-load/click triggers and frequency/logged-in rules; Pro supplies implementations for the corresponding Free mocks, under the same identifier and with the same parameter keys. Source registries and `get_name()` values, rather than translated labels, identify stored configuration. Rule/trigger `params` retain their type-owned shapes. Saving refuses a trigger or rule whose type is not registered; a Free placeholder for a Pro type counts as registered, so a popup built with Pro can still be edited without it. The `logged_in` rule means "hide for logged-in users".

Parameter keys, by identifier: `page_load` (`delay`), `click` (`selector`), `scroll` (`percentage`), `inactivity` (`delay`), `scroll_to_element` (`selector`), `on_popup_close` (`popup_id`: the source popup's id, `0` or absent meaning any other popup), `frequency_cap` (`max`), `once_per` (`period`), `devices` (`allow`), `schedule` (`start`, `end`), `page_views` and `sessions` (`count`), `referrer` (`url`), `browser` (`browsers`), `roles` (`roles`). `exit_intent` and `logged_in` take none.

Schedule `start` and `end` are stored as the `YYYY-MM-DDTHH:mm` wall-clock text the administrator entered and mean that time in the site's timezone (`wp_timezone()`). They reach the browser as offset-qualified ISO 8601 instants, and a popup whose `end` has passed is not delivered at all.

A trigger opens its popup automatically at most once per page view; click triggers, open links and the JavaScript API are not limited. A client-side rule with no handler blocks the popup's triggers (it fails closed, as the server does for an unavailable rule).

The following `DisplayKeys` values are stored in Elementor page settings and consumed by `DocumentDisplay`. Their authority is `src/Modules/Popups/Support/DisplayKeys.php`; defaults are in `DisplayDefaults.php`:

`elemacy_position`, `elemacy_sticky`, `elemacy_width`, `elemacy_z_index`, `elemacy_offset_x`, `elemacy_offset_y`, `elemacy_overlay_color`, `elemacy_overlay_opacity`, `elemacy_overlay_blur`, `elemacy_animation_in`, `elemacy_animation_out`, `elemacy_close_button`, `elemacy_auto_close`, `elemacy_close_on_overlay`, `elemacy_close_on_esc`, `elemacy_prevent_scroll`.

The animation values are `none`, `fade`, `slide-down` and `slide-up`, for both directions. Slide Down moves the popup downward and Slide Up upward, whether entering or leaving. The position values are `center`, `top`, `bottom`, `top-left`, `top-right`, `bottom-left`, `bottom-right` and `custom`.

Responsive variants and legacy display settings must continue following `DocumentDisplay` and the existing popup positioning spec. `elemacy_animation_in/out` here belong to native popup transitions, not the excluded Animations module.

## Popup page markup, events and JavaScript

Authors, themes and extensions use these on the page. They are permanent: from 1.0 they change only by adding to them.

- **Open a popup**: a link whose `href` is `#elemacy-popup-{id}` (an Elementor button's link field, for instance) or any element with `data-elemacy-popup-open="{id}"`. Opening skips the popup's rules and frequency bookkeeping, and never navigates, scrolls or changes the address bar. A link to a popup that is not on the page does nothing.
- **Close a popup**: inside a popup, a link to `#elemacy-popup-close`, an element with the class `elemacy-close-popup`, or an element with `data-elemacy-popup-close`. The unprefixed `.elemacy-open-popup` with `data-popup-id` was never released and is not supported.
- **Events** on `document`: `elemacy-popup:shown` and `elemacy-popup:destroyed` with `detail.id` (the numeric popup id), and `elemacy-popup:closed` with `detail.id` and `detail.wasShown` (`true` when the popup had finished opening before this close). Destroying a popup dispatches `destroyed` only.
- **`window.ElemacyPopups`**: `open(id)`, `close(id)`, `closeAll()`, `destroy(id)`, `registerTrigger(type, handler)` and `registerRule(type, handler)`. Every function taking an id accepts a number or its string form. A trigger handler is `fn(id, params, api)`: `api.open()` opens the popup automatically (once per page view) and `api.onCleanup(fn)` registers what to release once the trigger can no longer fire. A rule handler is `fn(rule, config, store)` and returns `false` to block.
- **`window.elemacyPopupsExtensions`**: an array to `push` a function onto, before or after the popup runtime loads; each is called once with `window.ElemacyPopups`. This is how Pro registers, so it works under any script order. A popup's automatic and click triggers start only once every trigger and rule type it uses has a handler.
- **Config**: `window.elemacyPopups` (`popups`, `i18n`, and `debug` when `WP_DEBUG` is on, which makes the runtime name in the console a popup waiting for a missing handler). Pro adds `window.elemacyPopupsAnalytics` (`rest_url`) and `window.elemacyVisitCounts` (`pageViews`, `sessions`).
- **Browser storage**: `localStorage[elemacy_popup_{id}]` (`shown_count`, `last_shown_at`) and `sessionStorage[elemacy_popup_{id}]` for frequency; Pro's `elemacy_pageviews`, `elemacy_sessions` and `sessionStorage[elemacy_session_counted]` for visit counts.
- **Root markup**: `[data-elemacy-popup-id]` roots with the classes `elemacy-popup`, `elemacy-popup--{type}`, `is-open`, and the box and scroll elements `elemacy-popup__box`, `elemacy-popup__scroll`, plus `elemacy-popup__close`. A top bar that shows as soon as the page loads is printed already open (`data-elemacy-prerendered`); one printed late because the theme never calls `wp_body_open` carries `data-elemacy-relocate="top"`.

Condition identifiers come from `src/Conditions/*/get_name()` and Pro's matching implementations: `general/entire_site`, `general/front_page`, `general/not_found`, `general/search_results`, `general/blog`, `singular/post_type`, `singular/by_author`, `singular/specific_post`, `singular/child_of`, `archive/post_type`, `archive/all`, `archive/date`, `archive/author`, `search/all`, `search/post_type`, plus `singular/in_tax/{taxonomy}` and `archive/tax/{taxonomy}` for registered taxonomies. `tests/architecture/persisted-identifiers.php` guards each one. Preserve the full path when a predicate moves or is unavailable; do not replace an unknown condition with an empty list.

## HTTP endpoints

All routes below currently use the `elemacy` REST namespace. The normal pretty-permalink base is `/wp-json/elemacy`; clients must use WordPress's generated REST URL so plain permalinks and subdirectory installs work. Braced parameters represent the existing registered parameter pattern, not a new URL version.

| Source config | Methods and relative paths | Access policy |
| --- | --- | --- |
| `F/src/Config/api.php` | `GET /modules`; `PUT /modules/{name}` | Admin middleware |
| Same | `GET /conditions/types`; `GET /conditions/search` | Admin middleware |
| Same | `GET /library/template-types`; `GET, POST /library/templates`; `GET, PUT, DELETE /library/templates/{id}`; `POST /library/templates/{id}/duplicate` | Admin middleware |
| `F/src/Modules/Widgets/Config/api.php` | `GET /widgets`; `PUT /widgets/{name}/toggle` | Admin middleware |
| `F/src/Modules/ThemeBuilder/Config/api.php` | `GET /theme-builder/template-types`; `GET, POST /theme-builder/templates`; `GET, PUT, DELETE /theme-builder/templates/{id}`; `POST /theme-builder/templates/{id}/duplicate` | Admin middleware |
| `F/src/Modules/Popups/Config/api.php` | `GET /popups/types`; `GET /popups/triggers`; `GET /popups/rules`; `GET, POST /popups`; `GET, PUT, DELETE /popups/{id}`; `POST /popups/{id}/duplicate` | Admin middleware |
| `P/src/Core/License/Config/api.php` | `GET /license`; `POST /license/activate`; `POST /license/deactivate` | Auth and admin middleware |
| `P/src/Extensions/Popups/Config/api.php` | `GET /popups/{id}/analytics` | Admin middleware |
| Same | `POST /popups/{id}/track` | Public tracking middleware: no nonce (a nonce in a cached page expires while the page is still served), the request is size-bound and rate-limited per client address, and only a deliverable popup can be counted. `tests/integration/route-authorization.php` pins this as the only public route. |

Frontend `admin-ajax.php` actions from `Modules/Widgets/Config/ajax.php`:

| Action | Nonce action |
| --- | --- |
| `elemacy_widgets_loop_grid_pagination` | `elemacy_ajax_nonce` |
| `elemacy_widgets_form_submit` | `elemacy_widgets_form_submit` |

Both actions serve guests and authenticated users. The public nonce is request hygiene, not authorization; each handler applies its own application policy. The successful loop response keys are `items` and `pagination`; the loop request is the signed envelope described in `docs/ARCHITECTURE.md`.

## Shared PHP and admin entry points

`Core\Container::{instance,make,singleton,bind}` is the composition surface. The loop layer's canonical classes live under `Elemacy\Loop` (`Contracts\LoopDataSourceInterface`, `Contracts\LoopItemInterface`, `LoopContext`, `LoopDataSourceRegistry`, `Rendering\LoopItemStyles`, `Placement\PlacementResolverRegistry`, `Pagination\*`); ACF access under `Elemacy\Integrations\Acf`; the HTTP layer under `Elemacy\Http`; everything that calls Elementor under `Elemacy\Elementor`. There are no alias paths.

Free owns the admin mount `#elemacy_root` and the `window.ElemacyShared` bridge (contract version `AdminScripts::CONTRACT_VERSION`, the `elemacy:admin-ready` event). Popup bootstrap globals are `window.elemacyPopups` and Pro's `window.elemacyPopupsAnalytics`. Version these surfaces deliberately; never rename them for tidiness.

Pro's license policy (`P/CONTRIBUTING.md`, `Core/License/LicenseManager.php`) is a contract: the license entitles a site to updates and support only, every Pro capability registers whenever Pro is active, and revalidation transport failures keep the cached state (see `P/DEVELOPER.md`).

## Public hook inventory

Every `Core\Hooks` constant. New hooks are added there; a consumer never uses a raw string.

| Constant | Hook value |
| --- | --- |
| `LOADED_ACTION` | `elemacy/action/loaded` |
| `REGISTER_MODULES_ACTION` | `elemacy/action/register_modules` |
| `REGISTER_PROVIDERS_ACTION` | `elemacy/action/register_providers` |
| `VERSION_UPDATED_ACTION` | `elemacy/action/version_updated` |
| `LIBRARY_DUPLICATE_EXCLUDED_META_FILTER` | `elemacy/filter/library/duplicate_excluded_meta` |
| `LIBRARY_CHANGED_ACTION` | `elemacy/action/library/changed` |
| `LIBRARY_TYPES_REGISTER_ACTION` | `elemacy/action/library/types/register` |
| `TEMPLATE_ASSETS_COLLECT_ACTION` | `elemacy/action/template_assets/collect` |
| `ADMIN_SCRIPT_DATA_FILTER` | `elemacy/filter/admin_script_data` |
| `ENQUEUE_ADMIN_SCRIPTS_ACTION` | `elemacy/action/enqueue_admin_scripts` |
| `FRONTEND_SCRIPT_DATA_FILTER` | `elemacy/filter/frontend_script_data` |
| `WIDGETS_REGISTER_FILTER` | `elemacy/filter/widgets/register` |
| `THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER` | `elemacy/filter/theme_builder/suppress_native_chrome` |
| `THEME_BUILDER_LOCATIONS_REGISTER_ACTION` | `elemacy/action/theme_builder/locations/register` |
| `THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION` | `elemacy/action/theme_builder/compatibility/register` |
| `LOOP_DATA_SOURCES_REGISTER_ACTION` | `elemacy/action/loop/data_sources/register` |
| `LOOP_PLACEMENT_RESOLVERS_REGISTER_ACTION` | `elemacy/action/loop/placement_resolvers/register` |
| `LOOP_CURRENT_QUERY_ARGS_FILTER` | `elemacy/filter/loop/current_query_args` |
| `QUERY_RESOLVERS_REGISTER_ACTION` | `elemacy/action/query_control/resolvers/register` |
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

Pro's own hook: `ElemacyPro\Extensions\Popups\Services\TrackingRateLimiter::CLIENT_IP_FILTER` (`P/src/Extensions/Popups/Services/TrackingRateLimiter.php`), `elemacy/filter/popups/track_client_ip`. It receives the connecting address (`REMOTE_ADDR`) and returns the real client address, for a site behind a reverse proxy or CDN that does not rewrite it. Forwarding headers are never trusted unless the site returns them here. An IPv6 client is counted by its /64 network.

The pre-1.0 `elemacy/filter/theme_builder/template_types` filter is retired: it only changed the type picker, and a type added through it could not be saved. Register a `TypeDefinition` instead.

### Opting a theme out of native chrome replacement

A configured Header or Footer template replaces the active theme's own header or footer, whichever adapter serves the theme: the generic one (which loads the theme's `header.php`/`footer.php` and discards the output), a theme-specific one, or the block-theme one (see `docs/ARCHITECTURE.md` § Native chrome replacement). `THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER` is the escape hatch for all of them. It is applied at most once per side per request, with `true` and the side (`'header'` or `'footer'`), and not at all for a side that has no template. Returning `false` leaves that side to the theme: the theme's own chrome renders and the Elemacy template for that side does not. The other side is unaffected. An add-on adapter gets the same behaviour by asking `ThemeBuilderManager::replaces_native_chrome($side)` instead of reading the template id.

```php
add_filter('elemacy/filter/theme_builder/suppress_native_chrome', function (bool $suppress, string $side): bool {
    return 'header' === $side ? false : $suppress;
}, 10, 2);
```
