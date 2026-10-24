# WordPress.org listing review — October 21, 2026

The listing was revised against the current Free and Pro source trees. This is a documentation and asset review; it does not claim a new runtime test of every feature.

## Material corrections

- Removed Pro Animations / GSAP claims. Pro's module configuration is empty in this release; its live extensions provide conditions, popups and ACF repeater loop sources.
- Replaced the seven-field form list with all 14 registered types.
- Corrected individual email placeholders to `[field id="email"]`; `[all-fields]` remains supported.
- Added free taxonomy Terms and Users loop sources, Current Query, nested layouts, Load More and Infinite Scroll.
- Documented free taxonomy and user dynamic tags as current features, removing the old coming-soon claim.
- Added the Pro ACF Repeater source, its four source choices, nesting, and separate ACF field-type dependencies.
- Clarified that the ACF widgets are free but their repeater/gallery field types need ACF Pro or compatible support; Elemacy Pro does not bundle ACF Pro.
- Corrected template setup: Loop Items belong in Template Library; published 404 templates target their location without include rules. Free includes Search Results targeting.
- Described all three Search widget skins and optional post-type scope.
- Separated free page-load/click triggers and two free visibility rules from advanced Pro triggers/rules.
- Removed unimplemented roadmap promises, universal speed claims, and unnecessary superlatives. Current form limitations are stated directly.
- Clarified Pro licensing: updates and support require entitlement; feature registration is not license-gated.
- Preserved contributor/version/requirements metadata and the exact 24 screenshot captions in their existing order. Initial-release changelog entries now reflect the implemented field types, data sources and dynamic tags.

## Source map

Paths below are relative to the Free plugin root unless marked Pro.

| Listing area | Source checked |
| --- | --- |
| Requirements | `elemacy.php`, `src/Core/CompatibilityChecker.php`, `docs/ELEMENTOR-COMPATIBILITY.md` |
| Modules and widgets | `src/Config/modules.php`, `src/Modules/Widgets/Config/widgets.php` |
| Theme templates and preview | `src/Modules/ThemeBuilder/Config/documents.php`, `src/Modules/ThemeBuilder/ThemeBuilder.php`, `src/Modules/ThemeBuilder/Documents/` |
| Theme compatibility | `src/Modules/ThemeBuilder/Compatibility/CompatibilityManager.php`, `ElementorProLocations.php` in the same folder |
| Free and Pro conditions | `src/Config/conditions.php`; Pro: `src/Extensions/Conditions/Config/conditions.php` |
| Template Library / Loop Item ownership | `src/TemplateLibrary/`, `src/Modules/Widgets/Widgets.php` |
| Loop data and controls | `src/Modules/Widgets/Widgets.php`, `DataSources/` and `Widgets/LoopGrid.php`, `LoopCarousel.php`, `LoopWidget.php` within that module |
| Nested loops | `src/Loop/Placement/NestedLoopPlacementResolver.php`, `src/Loop/Rendering/TemplateRecursionGuard.php` |
| Form fields and actions | `src/Config/form_field_types.php`, `src/Config/form_actions.php`, `src/Forms/Actions/EmailAction.php`, `src/Forms/Services/EmailTemplate.php` |
| Form validation, spam controls and storage | `src/Forms/Http/SubmissionController.php`, `src/Forms/Services/SubmissionService.php`, `SubmissionValidator.php`, `docs/STORAGE.md` |
| Popup formats, document controls, triggers and rules | `src/Modules/Popups/Support/PopupTypes.php`, `Documents/PopupDocument.php`, `Config/triggers.php`, `Config/rules.php`, frontend engine |
| Dynamic Tags | `src/Modules/DynamicTags/Config/tags.php`, `Services/User.php`, tag implementations |
| ACF widgets | `src/Modules/Widgets/Widgets/Acf/`, `src/Integrations/Acf/AcfRepeaterResolver.php` |
| Search, navigation and logo | `src/Modules/Widgets/Widgets/Search.php`, `NavMenu/Controls/`, `SiteLogo.php` |
| Custom CSS | `src/Modules/CustomCss/Services/CustomCssManager.php` |
| Pro feature scope | Pro: `src/Config/modules.php`, `src/Config/extensions.php`, `src/Extensions/Popups/`, `src/Extensions/LoopDataSources/`, `src/Extensions/Conditions/` |
| Pro licensing and data retention | Pro: `DEVELOPER.md`, `src/Core/ElemacyPro.php`, `src/Core/License/LicenseBootstrap.php`, `uninstall.php`; Free: `uninstall.php` |

## Copy and search decisions

The opening introduces the pain of assembling a site with separate tools, explains the outcome, and exposes the free feature set before detailed sections. The rest follows a building journey: site structure, repeatable content, forms, popup timing, connected data and finishing touches. FAQs answer practical setup and dependency questions.

The title, 140-character plain-text summary, five relevant tags and descriptive subheadings cover real search intent: Elementor addon, theme/header/footer builder, popup builder, contact form builder, loop grid/carousel, dynamic tags and ACF. Search terms are used where they explain a feature; no competitors' names are used as tags and no repeated keyword blocks were added.

This improves relevance and readability but cannot promise a first-page position. Ranking also depends on factors beyond readme copy. Guidance consulted:

- [WordPress.org readme format, short description and tags](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/)
- [WordPress.org plugin guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
- [Google: helpful, reliable, people-first content](https://developers.google.com/search/docs/fundamentals/creating-helpful-content)
- [WordPress.org listing asset sizes](https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/)

The listing icon preserves the existing monogram in an editable SVG with 128- and 256-pixel PNG fallbacks. Listing assets remain outside the distributable plugin package's allowlisted trees.
