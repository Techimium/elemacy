# Elemacy architecture

Elemacy is an Elementor addon. Elementor owns the editor, the element tree and template rendering; Elemacy adds a template library, display conditions, a theme builder, popups, loop widgets, forms, dynamic tags and custom CSS on top of it, all persisted in native WordPress storage. Elemacy Pro extends Free through published hooks and contracts and never the other way round.

This document is the map. `docs/CONTRACTS.md` lists the identifiers that must never change, `docs/STORAGE.md` the stored shapes, `docs/ELEMENTOR-COMPATIBILITY.md` every Elementor API touched, and `docs/RELEASE.md` how a release is verified.

## Folder map

| Folder | Responsibility | May import |
| --- | --- | --- |
| `src/Core/` | Plugin lifecycle and composition: bootstrap (`Elemacy`), `Module`, `ModuleManager`, `Container`, `Hooks`, providers, constants, migrations, multisite batching, capabilities, cache purging, admin menu and scripts | `Http`, `Elementor`, shared domains, `Support` |
| `src/Http/` | The in-house transport layer: REST `Route`, `AjaxRouter`, request/response objects, middlewares, validation rules, `Sanitizer`, exceptions, resources and result DTOs | `Core\Container`, `Support` |
| `src/Elementor/` | Everything that calls Elementor's PHP API on behalf of the rest of the plugin: template rendering and preparation, classic and atomic CSS, editor preview, previewable documents, query controls, the widget base class, and the compatibility probes and adapters that isolate version-sensitive calls | `Core`, `Http`, shared domains |
| `src/Loop/` | The loop domain: source contract and registry, loop items and context, render pipeline, pagination security model, placement resolvers, limits | `Core`, `Http`, `Elementor`, `TemplateLibrary`, `Integrations` |
| `src/Conditions/` | Display conditions: registry, evaluator, repository, DTOs, built-in predicates | `Core`, `Http` |
| `src/TemplateLibrary/` | The one library post type and its types, persistence primitives, resolver and cache | `Core`, `Http`, `Conditions` |
| `src/Integrations/` | Optional third-party plugins (ACF today), each behind its own boundary | `Core` |
| `src/Modules/<Name>/` | User-facing features. Each module is a `Module` with a consistent inner layout | any shared layer, never another module |
| `src/Support/` | Small stateless helpers | nothing above it |
| `src/Config/` | The composition root's declarations: module list, shared routes, built-in conditions | may name modules |

Inside a module the folders mean the same thing everywhere: `Config/` (routes, AJAX actions, document classes), `Controllers/` (HTTP handlers), `Services/` (application services), `Requests/` and `Resources/` (HTTP input and output shapes), `DTO/`, `Documents/` (Elementor documents), `Widgets/` (Elementor widgets), `Support/` (module-local helpers), `Views/` and `assets/`.

## Dependency rules

Shared layers never import a feature module. A feature module never imports another module's classes; modules cooperate through the shared layers' contracts and the hooks in `Core\Hooks`. Only `src/Elementor/` and the Elementor-instantiated classes inside modules (widgets, documents, dynamic tags, controls) reference Elementor's PHP API; `Loop`, `Conditions` and `TemplateLibrary` obtain rendering, CSS, preview and capability probes from the Elementor layer. Production source never references planning documents.

`php tests/architecture/check.php` enforces all of this on every push. It has no allowlist: an exception is a violation.

## Boot lifecycle

1. `elemacy.php` loads the autoloader and calls `Elemacy::get_instance()`. The constructor attaches `Core\SiteInstaller`'s hooks (activation, new-site seeding, resumed network seeding), subscribes its own core provider to `Hooks::REGISTER_PROVIDERS_ACTION`, and waits for `plugins_loaded`.
2. `Elemacy::init()` runs once per request. It checks requirements (Elementor present and supported), has `SiteInstaller` seed the site's defaults if missing, fires `REGISTER_PROVIDERS_ACTION` so Free and Pro queue their providers, then runs every provider's `register()` before any provider's `boot()`. Booting at most once is the composition root's job, not the provider's: `init()` runs once, so a provider does not defend itself against a second call and an add-on's provider is correct by default. Two instances of one provider class are two contributions and each boots. `Elemacy` itself only connects the parts; what Core attaches to WordPress (admin menu, REST/AJAX routes, library types, assets, Elementor integration) lives in the bootstraps `Core\Providers\CoreServiceProvider::boot()` creates.
3. `Core\ModuleLoader` loads modules into the container-shared `ModuleManager`: it registers the shared `ModuleCatalog`'s modules, fires `Hooks::REGISTER_MODULES_ACTION` for add-on modules, then calls `ModuleManager::init_modules()`. That resolves each module's state from the stored preferences, runs every active module's `register()` before any module's `boot()`, then each module's routes and assets. A module is a service provider with an identity: `register()` binds what it shares, `boot()` attaches its hooks. `Elemacy::get_module_manager()` returns the same shared manager (empty before `init()`), and `ModuleManager` is never bound in a provider, because a binding made during `init()` would replace an instance a caller already holds.
4. Which modules are active is owned by the modules and the administrator: `elemacy_active_modules` stores only explicit `slug => bool` choices, and a module with no recorded choice follows its own `Module::is_default_active()`, so a default-active module added by a later release reaches existing sites. Seeding stores no choices. A stored list of slugs (the pre-release shape) reads as exactly that active set. Once released, a module's declared default is frozen: flipping it changes every site that never toggled it. `Core\ModuleCatalog` enumerates the plugin's own modules for `ModuleLoader`; it reads `src/Config/modules.php` with a plain `require` and caches the instantiated modules, because `require_once` would hand a second reader `true` instead of the array.
5. Site setup runs where the bootstrap does not: the activation request and `plugins_loaded@1` happen before any provider registers. `SiteInstaller` and everything it takes are therefore built by container auto-wiring alone and are never bound in a provider.
6. Registrations that need translated labels (library types, admin menus, data sources, placement resolvers) hook `init`; anything an add-on contributes hooks `Hooks::LOADED_ACTION` or a domain registration action, all of which fire after every module has booted.

Pro follows the same shape: `ElemacyPro` subscribes a provider to `REGISTER_PROVIDERS_ACTION`, and its extensions register into Free's registries on `LOADED_ACTION` whenever the Free module they extend is active. Pro's license covers updates and support; no feature depends on it.

## Composition and injection

`Core\Container` is a small reflection container with explicit bindings. `make()` builds a fresh object, `singleton()` a shared one, and `bind()` registers a factory with a shared or fresh lifetime. Constructor parameters are auto-wired by class type; a required built-in parameter needs a factory, because scalar configuration belongs to a binding, not to reflection.

The rules:

- A service (anything with dependencies, shared state, or a lifetime worth controlling) is obtained from the container with `make()` or `singleton()`, not `new`, so a provider's `register()` can override it with `bind()`. Providers build their bootstraps this way too (`$container->make(X::class)->register_hooks()`). Value objects, DTOs, and classes Elementor or WordPress instantiate are still built with `new`.
- Anything the container builds (controllers, application services) declares its dependencies as required constructor parameters. No `?Dependency $x = null` defaults there: auto-wiring resolves a class-typed parameter even when it has a default, so the default never applies.
- Anything documented as overridable through the container is a public contract: keep its class name and behavior stable.
- Every process-wide registry keeps its `::instance()` accessor for hook callbacks and is also bound in the container by the layer or module that owns it, so a container-built service always receives the populated singleton and never a fresh empty copy.
- A shared registry accepts a real implementation superseding a mock stand-in, and rejects the reverse. Free ships mocks for the identifiers Pro implements; Pro registers after Free, so the real one wins. A mock arriving after a real implementation can only downgrade a live feature, so `Core\Capabilities\RegistryIdentifierGuard` refuses it rather than trusting registration order.
- `Container::instance()` is reached for only inside classes that Elementor or WordPress instantiate (widgets, documents, dynamic tags, hook callbacks) and inside bootstrap. Application services never locate their own dependencies.
- Route and AJAX handlers are resolved through the container lazily, inside the transport error boundary.

## The Elementor boundary

`Elementor\Rendering\TemplateRenderer` renders a foreign document (a header, footer, popup or loop item) wherever it is needed. `prepare()` renders once per (document, context, placement) early in the request, at `wp_enqueue_scripts`, so the widgets inside can queue their scripts and styles before WordPress prints them, and caches the fragment for the actual output position. It snapshots and restores `$post`, `$wp_query`, Elementor's document stack, the editor flag and the output-buffer depth, because Elementor's own pipeline restores none of them when a widget throws.

`Elementor\Compatibility\Capabilities` is the single place that asks whether the installed Elementor exposes an internal class; the adapters beside it own the version-sensitive calls behind those probes, so a future Elementor change is absorbed in one file rather than several. The supported range is verified by CI against the minimum and current versions and recorded in `docs/ELEMENTOR-COMPATIBILITY.md`.

Classic CSS and atomic CSS are separate renderers; classic dynamic CSS never depends on the optional atomic traversal being present.

## Shared domains

**Template library.** One post type, `elemacy_library`, holds every builder's documents; `_elemacy_template_type` says which. `TypeRegistry` describes each type (group, owning module, Elementor document class, availability). `LibraryRepository`, `DocumentWriter` and `DocumentDuplicator` own native persistence, ownership checks, Elementor document-type synchronization and compensation when a required write fails; the block, theme and popup services keep only what is specific to them and announce changes through `LibraryChanged`. `TemplateResolver` chooses the template for a location from `ResolverCache`, a rebuildable, non-autoloaded candidate index protected by generation checks and an expiring rebuild lock.

**Conditions.** Flat include/exclude matching that mirrors Elementor: includes OR together, any matching exclude vetoes, an exclude-only set means everywhere. A saved condition whose implementation is unavailable suspends the document instead of widening its targeting; `FeatureAvailability` distinguishes "unknown identifier", "extension inactive" and "no client handler" from a predicate that merely evaluated false.

**Template precedence.** When several templates of one type match a request, the most specific match wins — not the newest. Each condition declares how narrowly it targets, through `ConditionInterface::get_specificity(ConditionRuleDTO $rule)`, as one of four ordered `Specificity` levels:

| Level | Targets | Examples |
| --- | --- | --- |
| `SITE` | everything | Entire Site |
| `SECTION` | a whole family of requests | All Archives; Singular or Specific Post with no value |
| `TYPE` | one kind of request | Singular/Archive with a post type; Blog; 404; Search Results; an archive condition with no specific value |
| `OBJECT` | one identified object | Specific Post; Child Of; Front Page; an archive condition carrying a term, author or date |

The level is taken from the *rule*, not just the condition class, because a condition's reach usually depends on its stored value: a taxonomy archive condition with no term targets a whole taxonomy, the same condition with a term targets one archive.

`ConditionEvaluator::match()` returns the highest level among the inclusions that actually matched, or null when the set does not match at all. A matching exclusion vetoes outright and never contributes a level, so a site-wide template carrying a narrow exclusion never outranks a genuinely narrow one. `TemplateResolver::resolve()` therefore evaluates every candidate of the type rather than stopping at the first match, and keeps the highest-scoring one; candidates stay in the index's newest-first order, which is the tie-break inside one level. Net ordering: **most specific, then newest, then highest id**. A template with no conditions at all remains the global fallback, used only when nothing matched.

A condition that does not override `get_specificity()` — an extension's included — reports `SITE`, so it still registers and still matches and simply never outranks a condition that does declare a level. The resolver never enumerates condition identifiers, so an extension participates in ordering by declaring its level and nothing else.

Collect-all resolution (`resolve_group()`, used by popups) is unaffected: every matching item is still returned, unordered and unfiltered by specificity.

**Loop.** A `LoopDataSourceInterface` supplies items for the Loop Grid and Carousel and registers its own Elementor controls; sources register through `LoopDataSourceRegistry` and add-ons through `Hooks::LOOP_DATA_SOURCES_REGISTER_ACTION`. `LoopRenderer` renders one item: template check, recursion guard, base CSS once per template, the item's native scope entered and restored in `finally`, per-item dynamic CSS scoped by `LoopRenderIdentity` so the same post in two widgets never shares a style scope. Public pagination never trusts the browser: a request carries only the owner document, widget id, page, a fingerprint of the saved definition and a signed context; `PublishedLoopDefinitionResolver` reads the saved widget, `PublicRenderPolicy` checks publication and source policy, and only then does a source run its query, bounded to 100 items. Placement resolvers (page, nested loop, Theme Builder, popup) prove the owner is the document actually rendering, and the Theme Builder placement supplies the archive's own verified query facts so "Current Query" keeps the archive's term, author, date or search term.

**Registries describe executable features.** Every library type, loop source, condition, popup rule and popup trigger is registered as a descriptor with its identifier, its parameter schema and its availability. The REST layer validates saved values against those descriptors and the admin UI derives its choices from them, so an extension's type appears everywhere by registering once and a reserved or duplicate identifier is rejected at registration.

**Integrations.** ACF access lives in `Integrations\Acf`; `AcfRowScope` advances ACF's native row cursor reversibly so nested and repeated rows restore the exact enclosing state.

## Transport

Every REST route declares `public_access()` or `require_capability()`; a route without a policy is not registered. Group prefixes, middleware and policies compose through every nesting level. Handlers are built inside the error boundary and every `Throwable` becomes the safe response envelope without a stack trace. The two intentionally public handlers, loop pagination and form submission, apply their own application policies: a nonce is request hygiene, not authorization.

## Popups

Matched popups are prepared before the head and footer deadlines and emitted later from the prepared fragment. The frontend engine has explicit per-instance states (closed, opening, open, closing, destroyed) with cancellable transitions, initializes inserted Elementor subtrees exactly once, and a single modal stack owns opening order, Escape, focus return and the shared scroll lock.

## Native chrome replacement

A configured Header or Footer template replaces the active theme's own chrome. How depends on the theme; `CompatibilityManager` picks exactly one adapter per request: a dedicated adapter registered for the theme's exact slug, else `BlockThemeCompatibility` for a block theme, else the generic `GlobalCompatibility`.

**The generic path discards native chrome; it never hides it.** On `get_header` (late, priority 1000, so other listeners such as Elementor's image-loading output buffer run first, as they would before the theme's own template) it:

1. requires `Views/theme-support-header.php`: the doctype, `<head>` with `wp_head()`, `<body>`, `wp_body_open()` and the Header template;
2. empties `wp_head` and `wp_body_open` with `remove_all_actions()`;
3. loads the theme's `header-{name}.php`/`header.php` through `locate_template(..., true, true)` into an output buffer and throws the output away.

`get_footer` mirrors it with `Views/theme-support-footer.php` (the Footer template, `wp_footer()`, `</body></html>`) and `wp_footer`. Because step 3 is a `require_once`, WordPress's own `get_header()`, which runs immediately after, finds the file already loaded and prints nothing. The theme's content template, sidebars and comments run normally; `template_include` is not taken over. When the theme has no `header.php`, `locate_template()` resolves WordPress's deprecated `theme-compat` stub, and that is discarded the same way. Each side is independent: replacing only the header leaves the theme's `footer.php` to close the document, and the reverse. Either way the page has one `<head>`, one `<body>` and one `</body></html>`.

**Why emptying the shell hooks is safe.** `remove_all_actions()` runs only after the shell has run that hook to completion, so every callback, other plugins' included, has already run once and its output is in the response. Emptying the hook only stops the discarded native template from running those callbacks a second time and repeating their side effects. The observable contract is that each head, body-open and footer callback runs exactly once. `tests/php/run.php` pins the order (shell, then neutralize, then discard). `tests/integration/native-chrome-replacement.php` renders each page in a fresh process and counts a third-party callback on each hook. The admin bar needs nothing extra: WordPress renders it at the shell's `wp_body_open` and guards it against a second render.

**The trade-off.** Markup a theme opens in `header.php` is discarded with it. Most classic themes built on `_s`, Twenty Twenty-One among them, open their layout wrappers (`#page`, `#content`, `#primary`, `main`) in `header.php` and close them in `footer.php`. With both sides replaced the page stays balanced, but those wrappers are gone, so the content renders without them. With only the header replaced, the theme's footer closes wrappers that were never opened; browsers ignore the stray closing tags. With only the footer replaced, the wrappers are never closed, so the Footer template renders inside the theme's `main`. Elementor Pro's Theme Builder behaves the same way on these themes. Markup a theme opens in `header.php` and closes in its content template is lost with the discarded header too. So is non-visual output inside `header.php`, such as skip links or theme JSON-LD; it was equally invisible when the header was only hidden. `Hooks::THEME_BUILDER_SUPPRESS_NATIVE_CHROME_FILTER` (`docs/CONTRACTS.md`) is the escape hatch. It is asked once per side per request, and returning false leaves that side to the theme, as though no template were configured.

**The location wrapper composes unchanged.** `theme-builder-wrapper.php` calls `get_header()` when the theme has a real `header.php`, so this adapter handles it. When it has none, the wrapper requires the shell itself and `get_header` never fires. `ThemeBuilderManager` renders each side at most once per request either way, and never on Elementor Canvas.

**Dedicated adapters are an optimization, not a correctness requirement.** A classic theme without an adapter is fully supported through the generic path. An adapter only avoids loading a template that is then discarded, and keeps the theme's own chrome hooks in play. The shipped adapters stay, and a new one is one class plus one registration through `Hooks::THEME_BUILDER_COMPATIBILITY_REGISTER_ACTION`, with no edit to any other adapter or to the generic path. Ship one only where the theme exposes an action that carries its chrome **and** a callback on it that can be removed by reference, and only after verifying it against a real install of that theme. An adapter that removes some of a theme's chrome and leaves the rest is worse than the generic path.

## Free and Pro

Free owns shared infrastructure and works alone. Pro contributes through `Hooks::REGISTER_PROVIDERS_ACTION`, `ExtensionInterface` (`depends_on()` names the Free module, `register()` runs only when it is active), and the domain registration actions. The admin UI bridge, `window.ElemacyShared`, carries a contract version (`AdminScripts::CONTRACT_VERSION`, currently 1.0.0) and a capability list, and announces readiness with the `elemacy:admin-ready` event. Pro's loader accepts contract major 1 with the required capabilities and runtime fields, either already ready or delivered within five seconds; an unsupported or missing bridge shows a diagnostic and leaves Free usable. Pro builds against a pinned, types-only contract package (`src-react/contracts/`) rather than Free's source tree, keeps its own lockfile, ships only additive prefixed styles, and its production build rejects any bundled React. The guarded `tests/e2e/run-admin-contract.mjs` suite exercises both delivery orders, a delayed bundle, repeated readiness and an injected unsupported-major contract.

## Caches and schema

Request-level caches (matched popups, resolved templates, display settings) reset on library changes and on `switch_blog`.

**One page render is one request, even in a long-running process.** The caches the rendering pipeline keeps for a page — prepared foreign-document fragments (`TemplateRenderer`), loop base-style markers (`LoopItemStyles`), resolved templates (`TemplateResolver`), the render-once guard for header and footer (`ThemeBuilderManager`), matched popups (`PopupManager`) and registered asset ids (`TemplateAssetsRegistrar`) — each register a reset with `Core\RequestState`. It flushes them as the first callback on `wp`, which `WP::main()` fires once per page after the main query exists and before anything resolves or prepares a template. The fragment, style-marker and resolution caches also key every entry by the acting blog id, so a site never reads another site's entry and a `switch_to_blog()` round trip keeps the current site's.

`RequestState` is the entry point for anything that renders outside `WP::main()`: a WP-CLI command or batch job that renders several pages, or a test harness, calls `RequestState::flush()` before each page. A new request-scoped cache calls `RequestState::register()` from the code that owns it; a cache that does not register outlives its page in every such process. `elemacy_db_version` records the release last seen; `elemacy_schema_version` records completed data migrations and only `Migrator::run()` advances it, one completed step at a time, resumable and never lowered on downgrade. Pro owns `elemacy_pro_schema_version` independently.

## Invariants

- Persisted identifiers (post type, meta keys, option keys, widget names, source keys, document types, hook names, REST and AJAX action names) are listed in `docs/CONTRACTS.md` and never renamed by a code move.
- Hook names live in `Core\Hooks`; no raw hook string in a consumer.
- No custom tables. Every stored shape is documented in `docs/STORAGE.md` and changes only through a migration step.
- Release archives are staged from an explicit manifest with production autoload metadata, verified file by file, and installed into a clean WordPress before publication; unshipped code is not on the release branch.
- Uninstall deletes only plugin-owned data, through native deletion in bounded batches per site, and never schedules future work. Each plugin's `uninstall.php` runs without either bootstrap or the Composer autoloader, so it loads `Multisite\SiteBatchIterator` by path — which is why that class stays dependency-free (a plain array return, no DTO) and why Pro keeps its own copy rather than referencing Free's.
- Every phase of a release runs the full gate in `docs/RELEASE.md`.

## Rationale digest

Longer reasoning that used to live in code comments, by section name.

**Bounded network seeding.** Network activation seeds sites in batches of 100 and records a network-wide offset; ordinary requests resume the job and a site that is somehow still unseeded seeds itself on its first real visit. An unbounded loop over every site could exceed one request's execution time.

**One init per request.** `Elemacy::init()` guards the whole bootstrap, not only the provider phase, because provider subscribers construct a fresh provider instance every time they run; a second `do_action` would boot a brand-new instance regardless of the old one's own guard.

**Container lifetimes.** `make()` requesting freshness never overrides an explicit shared binding, and nested dependencies resolve as shared unless they have their own fresh binding, so a service never receives two copies of a registry by accident. Cycle detection is instance state so a cycle reached through a bound factory is caught exactly like one reached through reflection.

**Early preparation restores ambient state.** Elementor nests several output buffers with no `finally`; a widget that throws mid-render leaves them open and silently swallows the rest of the page. `TemplateRenderer` discards buffers back to the depth it saw before rendering and pops the document stack until it matches the snapshot, bounded so a corrupted stack cannot hang the request.

**Render identity.** The per-item CSS selector is derived from placement, template, source, item identity, occurrence and batch. The item's own domain identity is untouched and still reaches dynamic tags, so a post, term and user that share a numeric id never collide into one style scope, and an AJAX replacement never reuses a selector from page one.

**Signed pagination context.** A valid signature proves a placement resolver verified these host facts for this owner, widget and definition revision on this site; it never authorizes access by itself. The key derives from WordPress's auth salt and the token names the blog id, so it rotates with the salts and cannot be replayed on another site.

**Theme adapters own replacement without destroying hooks.** Hello Elementor's own `hello_elementor_header_footer` filter suppresses only its chrome; nothing is removed from `wp_head` or `wp_footer`, so other plugins' callbacks survive. The generic path empties a shell hook only after its own shell has run it; see § Native chrome replacement.

**Counter leases.** `add_post_meta(..., true)` is not a unique constraint, so a counter's first creation takes a 30-second database lease and rechecks under it; existing counters increment database-side. Legacy duplicate rows were replicas incremented together, so normalization keeps the oldest row at the largest value rather than summing.

**Feature availability is not truth.** A condition can be available and still evaluate false for this request. Only an unavailable required feature suspends a document, and its stored configuration is preserved for repair rather than dropped.

**Elementor capability probes.** A `true` from `Capabilities` only means the class exists; the adapters own the calls whose shape a future Elementor version could change, and the CI matrix is the evidence that they still behave.
