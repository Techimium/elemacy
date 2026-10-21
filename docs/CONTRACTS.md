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
- **Loop control IDs and their defaults**: every value-holding control of Loop Grid, Loop Carousel, the Loop Item document's preview section and every Free and Pro data source, pinned with its type and default by `tests/integration/loop-control-defaults.php` against `tests/integration/fixtures/loop-control-defaults.json`: § Saved module, widget and data-source names.
- **Dynamic tag names**: every value in § Dynamic tag names, saved inside `[elementor-tag name="..."]` references.
- **Form Builder**: the built-in field type and submit action keys, the form source key `elementor`, the Form widget's control IDs and their defaults, and the field and action setting keys: § Form Builder.
- **Popup trigger and rule identifiers and their parameter keys**, the **popup document setting names** (`DisplayKeys` and the close button style controls), the **position and animation option values**, and the browser storage key templates (`elemacy_{blog}_` and each popup's `elemacy_{blog}_popup_{id}`, declared in `Popups\Support\StorageKeys`): § Popup targeting and display settings. Pro's visit-counter storage keys (the site prefix followed by `pageviews`, `sessions` and `session_counted`) are pinned by `P/tests/php/run.php`, which also fails when a Pro trigger or rule differs from its Free placeholder in id or parameter keys.

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
| `elemacy_form_limit_*` | `src/Forms/Services/SubmissionRateLimiter.php`; form submission rate-limit rows (options, non-autoloaded), `elemacy_form_limit_<HMAC of the client IP>` holding `<window end>:<count>` |
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

**The data-source contract is frozen.** `Loop\Contracts\LoopDataSourceInterface` keeps its methods and their meanings from 1.0 on; a new ability arrives only as a separate optional interface a source may also implement, as `PublicLoopSourcePolicyInterface` and `PageUrlPaginatedSourceInterface` do, so a source written against 1.0 keeps loading. Visitors only ever see a source that implements `PublicLoopSourcePolicyInterface`: without it the loop renders in the editor, and a logged-in editor of the page sees a frontend notice that it cannot be shown to visitors. Its `get_public_settings($settings, $context, $page)` receives saved settings only, never request data, and two host facts the server decided for the request: `post_id` (the post of the nearest enclosing post loop item, else the viewed singular post) and `term_id` (the viewed term on a term archive), each present only while publicly viewable.

**Loop control IDs share one namespace.** Every source's controls live in the widget's single settings array, and Elementor silently drops a second control registered under an existing ID. An add-on source's control IDs start with its own source key. The IDs the built-in sources and the loop widgets use are reserved; `tests/integration/loop-control-defaults.php` fails on a clash, a rename, a removal, an unpinned control or a changed default. Changing one ships a `Core\Migrator` step and then a reviewed regeneration of the fixture (the command is in that test's docblock).

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

- **Open a popup**: a link whose `href` is `#elemacy-popup-{id}` (an Elementor button's link field, for instance). Opening skips the popup's rules and frequency bookkeeping, and never navigates, scrolls or changes the address bar. A link to a popup that is not on the page does nothing.
- **Close a popup**: inside a popup, a link to `#elemacy-popup-close`, or an element with the class `elemacy-popup-close`. Every class an author adds to control a popup follows `elemacy-popup-{action}` (Pro adds `elemacy-popup-convert`). The class `elemacy-close-popup`, the attributes `data-elemacy-popup-open`/`-close`/`-convert`, and the unprefixed `.elemacy-open-popup` with `data-popup-id` were never released and are not supported.
- **Events** on `document`: `elemacy-popup:opened` and `elemacy-popup:destroyed` with `detail.id` (the numeric popup id), and `elemacy-popup:closed` with `detail.id` and `detail.wasShown` (`true` when the popup had finished opening before this close). Destroying a popup dispatches `destroyed` only.
- **`window.elemacyPopups`** (the API): `open(id)`, `close(id)`, `closeAll()`, `destroy(id)`, `registerTrigger(type, handler)` and `registerRule(type, handler)`. Every function taking an id accepts a number or its string form. A trigger handler is `fn(id, params, api)`: `api.open()` opens the popup automatically (once per page view) and `api.onCleanup(fn)` registers what to release once the trigger can no longer fire. A rule handler is `fn(rule, config, store)` and returns `false` to block.
- **`window.elemacyPopupsExtensions`**: an array to `push` a function onto, before or after the popup runtime loads; each is called once with `window.elemacyPopups`. This is how Pro registers, so it works under any script order. A popup's automatic and click triggers start only once every trigger and rule type it uses has a handler.
- **Config**: `window.elemacyPopupsConfig` (`popups`, `storage_prefix`, `i18n`, and `debug` when `WP_DEBUG` is on, which makes the runtime name in the console a popup waiting for a missing handler). Pro adds `window.elemacyPopupsAnalyticsConfig` (`rest_url`) and `window.elemacyPopupsVisitCounts` (`pageViews`, `sessions`). Every global starts with `elemacy` in lower camel case, a global that only carries server data ends in `Config`, and no two differ only by letter case. The Elementor subtree helper is `window.elemacyElementorLifecycle`.
- **Browser storage**: every key starts with the site prefix `elemacy_{blog}_` (`{blog}` is the site id, so sites of one network sharing a browser origin never share counts; Free passes it to the browser as `storage_prefix`). `localStorage[elemacy_{blog}_popup_{id}]` (`shown_count`, `last_shown_at`) and `sessionStorage[elemacy_{blog}_popup_{id}]` hold frequency; Pro's `elemacy_{blog}_pageviews`, `elemacy_{blog}_sessions` and `sessionStorage[elemacy_{blog}_session_counted]` hold visit counts. `StorageKeys` is `@api` for Pro.
- **Root markup**: `[data-elemacy-popup-id]` roots with the classes `elemacy-popup`, `elemacy-popup--{type}`, `is-open`, and the box and scroll elements `elemacy-popup__box`, `elemacy-popup__scroll`, plus `elemacy-popup__close`. A top bar that shows as soon as the page loads is printed already open (`data-elemacy-prerendered`); one printed late because the theme never calls `wp_body_open` carries `data-elemacy-relocate="top"`.

Condition identifiers come from `src/Conditions/*/get_name()` and Pro's matching implementations: `general/entire_site`, `general/front_page`, `general/not_found`, `general/blog`, `singular/post_type`, `singular/by_author`, `singular/specific_post`, `singular/child_of`, `archive/post_type`, `archive/date`, `archive/author`, `search/all`, `search/post_type`, plus `singular/in_tax/{taxonomy}` and `archive/tax/{taxonomy}` for registered taxonomies. `tests/architecture/persisted-identifiers.php` guards each one. Each display check has exactly one identifier: every archive is `archive/post_type` with no value, every search results page is `search/all`, and `singular/specific_post` always names a post (`BaseCondition::requires_value()`). Preserve the full path when a predicate moves or is unavailable; do not replace an unknown condition with an empty list.

## Form Builder

The forms engine lives in `src/Forms/` (`Elemacy\Forms`); the Form widget (`elemacy-form`) is its Elementor UI and registers itself as the form source `elementor`. Everything below is 1.0 contract: it changes only by adding to it, and `tests/architecture/persisted-identifiers.php` guards the saved parts.

### Saved form settings

- **Field type keys** (the `type` of each `form_fields` row), in the editor's order from `src/Config/form_field_types.php`: `text`, `email`, `textarea`, `url`, `tel`, `number`, `date`, `time`, `select`, `radio`, `checkbox`, `acceptance`, `hidden`, `html`.
- **Submit action keys** (`submit_actions`), from `src/Config/form_actions.php`: `email`.
- **Row keys** every field has: Elementor's `_id`, `type`, `label`, `custom_id` (shown as "ID") and `width`. Shared field settings with a fixed kind (`Settings\Setting::STANDARD`): `placeholder`, `required`, `options` (one option per line, `Label` or `Label|value`), `field_value`. Type settings: `field_min`, `field_max`, `field_step` (Number), `min_date`, `max_date` (Date, `YYYY-MM-DD`), `acceptance_text`, `checked_by_default` (Acceptance), `field_html` (HTML). An enabled switcher saves `yes`.
- **Email action settings**: `email_to`, `email_subject`, `email_content`, `email_from_name`, `email_from`, `email_reply_to`. Every setting of an action starts with its key and `_`, and its editor section is `section_{key}`.
- **Widget controls**: `form_fields`, `labels_display`, `button_text`, `button_align`, `button_width`, `submit_actions`, and the style controls listed in the identifier check. `submit_actions` and the row `type` declare `save_default`, so a saved form never follows a later default; every other default is frozen by the defaults snapshot in the identifier check. A new widget starts with the fields `name`, `email` and `message`.

**Field ID.** A field's ID is its `custom_id` when that is 1-64 letters, digits, `_` or `-` and no earlier field claimed it; otherwise it is the row's `_id` (and, for malformed data only, `field_{index}`). `Forms\Definition\FieldIdResolver` decides it, for rendering and submission alike. Values, errors and email placeholders use it, so reordering fields never moves a value.

### Submission request and response

`POST admin-ajax.php` with:

| Field | Value |
| --- | --- |
| `action` | `elemacy_form_submit` |
| `form_source` | the form source key, `elementor` for the widget |
| `form_owner` | the post that stores the form (a page, post or library template) |
| `form_id` | the form's ID in its owner (the widget's element ID) |
| `form_fields[<field ID>]` | each value; a checkbox group posts `form_fields[<field ID>][]` |
| `elemacy_honeypot` | must be empty; when filled, the normal success answer comes back and nothing runs |
| `_wpnonce` | only from a logged-in visitor's page: a nonce for the action `elemacy_form_submit`, which attaches the user to the submission. Without a valid one the submission is processed as anonymous. |

Rendered markup holds nothing that expires or differs per visitor: no nonce, no signed token, no timestamp. The response is WordPress's JSON envelope. Success is `{"success": true, "data": {"message": ..., "data": {<action key>: ...}}}`, where the inner `data` appears only when an action returned some. Failure is `{"success": false, "data": {"message": ..., "errors": {<field ID or "form">: ...}}}` with status 400 (bad parameters, an unavailable form, an unregistered saved action, or an action's own failure message), 422 (field errors) or 429 (rate limit). Messages are plain text; the browser inserts them as text.

### Email placeholders

`[all-fields]` lists every field that submits a value, in field order, as `Label: value` (HTML fields are left out). `[field id="<field ID>"]` inserts one field's value; an unknown ID gives an empty string. Values are formatted by their field type (several choices joined with commas, an acceptance as "Accepted") and HTML-escaped. Reply-To takes a field ID, whose value must be a valid address, or a literal address.

### PHP extension points

Register field types, actions and form sources on the three `FORM_*_REGISTER_ACTION` hooks (§ Public hook inventory); each receives its registry, and a key that is already registered is refused with a `_doing_it_wrong` notice. The `@api` interfaces are `Forms\Contracts\FieldTypeInterface`, `FormActionInterface` and `FormSourceInterface`. Extend `Forms\Fields\AbstractFieldType` or `AbstractChoiceFieldType` rather than implementing the field interface directly, so later optional methods arrive with defaults. Settings are described with `Forms\Settings\Setting` (kinds `text`, `textarea`, `number`, `switcher`, `select`), which the widget turns into Elementor controls. An action receives a `Submission` (the `FormDefinition`, values by field ID, and `SubmissionMeta`: page URL, verified user ID or 0, time) and returns an `ActionResult`. It never reads the request and never uses `get_current_user_id()`.

### Browser runtime

- **Config**: `window.elemacyFormsConfig` (`ajaxUrl`, `i18n` with `required`, `error` and `network`, and `nonce` only for a logged-in visitor), printed with the `elemacy-form` script. The script and style handles are both `elemacy-form`.
- **Events** dispatched on the form element, bubbling: `elemacy-form:submit` before sending (cancelable: `preventDefault()` sends nothing), then `elemacy-form:success` or `elemacy-form:error`. `detail` is `{form, response}`, where `response` is the envelope's `data`, or `null` after a network failure.
- **Markup**: one delegated listener handles every `form[data-elemacy-form]` on the page, including forms added later. Each field wrapper carries `data-field-id`. The `elemacy-form*` classes style the form; they are not saved data.

## HTTP endpoints

Every route below, Free's and Pro's, lives under the versioned `elemacy/v1` REST namespace (`Route::set_namespace()` in `Core/Elemacy.php`); no route is registered under an unversioned `elemacy` namespace, and `tests/architecture/check.php` rejects an unversioned namespace, `rest_url('elemacy/...')` or route literal. The normal pretty-permalink base is `/wp-json/elemacy/v1`; clients must use WordPress's generated REST URL so plain permalinks and subdirectory installs work: the admin apps build every request from `api_base` (`rest_url('elemacy/v1/')`), and Pro's tracking URL is `rest_url('elemacy/v1/popups')`. An incompatible change ships as a new namespace such as `elemacy/v2`, alongside `elemacy/v1`. Braced parameters represent the existing registered parameter pattern, not a new URL version.

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

Frontend `admin-ajax.php` actions:

| Action | Registered by | Nonce action |
| --- | --- | --- |
| `elemacy_widgets_loop_grid_pagination` | `Modules/Widgets/Config/ajax.php` | `elemacy_ajax_nonce` (required) |
| `elemacy_form_submit` | `Forms\FormsBootstrap` | none required; `elemacy_form_submit` only proves a logged-in visitor's identity (§ Form Builder) |

Both actions serve guests and authenticated users, and each handler applies its own application policy. The loop's public nonce is request hygiene, not authorization. The successful loop response keys are `items` and `pagination`; the loop request is the signed envelope described in `docs/ARCHITECTURE.md`. The form endpoint deliberately takes no public nonce, so a cached page keeps submitting however old it is.

## Shared PHP and admin entry points

`Core\Container::{instance,make,singleton,bind}` is the composition surface. The loop layer's canonical classes live under `Elemacy\Loop` (`Contracts\LoopDataSourceInterface`, `Contracts\LoopItemInterface`, `LoopContext`, `LoopDataSourceRegistry`, `Rendering\LoopItemStyles`, `Placement\PlacementResolverRegistry`, `Access\*` for visitor eligibility and signed pagination, `Pagination\*` for the pagination UI, and one folder per built-in source under `Sources\` holding its query, public policy and settings); ACF access under `Elemacy\Integrations\Acf`; the HTTP layer under `Elemacy\Http`; everything that calls Elementor under `Elemacy\Elementor`. There are no alias paths.

Free owns the admin mount `#elemacy_root` and the `window.ElemacyShared` bridge (contract version `AdminScripts::CONTRACT_VERSION`, the `elemacy:admin-ready` event). Popup bootstrap globals are `window.elemacyPopupsConfig` and Pro's `window.elemacyPopupsAnalyticsConfig`. Version these surfaces deliberately; never rename them for tidiness.

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
| `FORM_FIELD_TYPES_REGISTER_ACTION` | `elemacy/action/forms/field_types/register` |
| `FORM_ACTIONS_REGISTER_ACTION` | `elemacy/action/forms/actions/register` |
| `FORM_SOURCES_REGISTER_ACTION` | `elemacy/action/forms/sources/register` |
| `FORM_VALIDATION_FILTER` | `elemacy/filter/forms/validation` |
| `FORM_ACTION_RUN_ACTION` | `elemacy/action/forms/action_run` |
| `FORM_SUBMITTED_ACTION` | `elemacy/action/forms/submitted` |
| `FORM_RATE_LIMIT_FILTER` | `elemacy/filter/forms/rate_limit` |
| `FORM_CLIENT_IP_FILTER` | `elemacy/filter/forms/client_ip` |
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
