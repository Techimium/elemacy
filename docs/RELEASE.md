# Releasing Elemacy

Every step is a command. The gate is the same on a developer machine and in CI; a release that has not passed it is not a release.

Paths: `F` is `wp-content/plugins/elemacy`, `P` is `wp-content/plugins/elemacy-pro`. Commands run from the plugin root unless a path is shown.

## 1. The gate

Run at every phase boundary of a change and before packaging.

Both plugins declare PHP 7.4. The authority for that floor is CI's `php -l` on PHP 7.4 (the `PHP 7.4 — lint, standards, unit tests` job in each repo's `ci.yml`): **the revision being released must have that job green in both repos.** No local PHP 7.4 is assumed; the architecture check's token scan is the fast local net and cannot see every construct (constructor promotion, named arguments, union types, `mixed`).

```bash
# Fast harness (both plugins)
php tests/php/run.php

# Coding standards (both plugins). PHPCompatibility is 9.x, which predates
# PHP 8: it cannot flag PHP 8-only syntax, so it is not the PHP 7.4 guard.
vendor/bin/phpcs -q

# Architecture check: layering, Elementor boundary, no planning references,
# no persisted identifier renamed without a migration, every built-in dynamic
# tag prefixed `elemacy-`, and no PHP 8-only token in shipped PHP (F)
php tests/architecture/check.php

# Release-gate regressions: verify-package.py rejects stale, dirty and manifest-less
# archives (F); verify-endpoints.py rejects unreachable and wrong-shape answers (P)
python3 tests/release/verify-package-test.py        # F
python3 tests/release/verify-endpoints-test.py      # P

# Static popup runtime suites (needs Chromium; ELEMACY_CHROMIUM overrides Playwright's cache)
cd tests/e2e && node run-popups.mjs        # F
cd tests/e2e && node run-popups-pro.mjs    # P

# Real WordPress bootstrap with both plugins active (any WP-CLI-reachable site)
wp --path=/path/to/wordpress eval 'echo "ok";'
wp --path=/path/to/wordpress plugin list --fields=name,status
```

Then the real WordPress and Elementor suites in a disposable site (see `tests/integration/README.md` for every option):

```bash
python3 tests/integration/setup.py --private-mysql --wordpress-source /path/to/wordpress \
    --port 8897 --with-pro --acf-plugin /path/to/advanced-custom-fields
export ELEMACY_TEST_ENV=/tmp/elemacy-integration-<token>/environment.json   # printed by setup
php tests/integration/run.php --exclude-group requires-acf-pro              # drop the flag with ACF Pro
cd tests/e2e && node run-builders.mjs && node run-loop-pagination.mjs \
    && node run-current-query-pagination.mjs && node run-current-query-editor.mjs \
    && node run-loop-load-more.mjs && node run-loop-load-more-editor.mjs \
    && node run-popup-assets.mjs && node run-form-submissions.mjs \
    && node run-editor-template-styles.mjs
```

Tear the site down afterwards:

```bash
mysqladmin --no-defaults --socket=/tmp/elemacy-integration-<token>/mysql.sock --user=root shutdown
rm -rf /tmp/elemacy-integration-<token>
```

## 2. Admin builds

```bash
cd src-react && npm ci && npm run build      # F and P
cd src-react && npm run lint                 # F
```

Pro's `assets/admin/admin-bundle-report.json` must list zero React modules. With both bundles built and the disposable site provisioned with `--with-pro`, run the admin contract suite:

```bash
cd tests/e2e && node run-admin-contract.mjs   # F
```

## 3. Package

Commit first: an archive is only accepted when it was built from a clean tree at exactly the revision being released.

```bash
cd src-react && npm run build:zip                                            # F and P
python3 src-react/scripts/verify-package.py build/elemacy-<version>.zip --check-missing-tools
python3 src-react/scripts/verify-package.py build/elemacy-pro-<version>.zip --check-missing-tools   # from P
python3 src-react/scripts/verify-endpoints.py                                # from P
```

`verify-package.py` checks the archive against its manifest and checksum, that it was built from a clean tree at the revision being released (`--release-revision`, default `HEAD`; an older revision is rejected as stale), that `composer.json` ships beside `vendor/`, that no development, test or unshipped file is inside, that the plugin header `Version`, `ELEMACY_VERSION` and the readme `Stable tag` are identical, that the readme `Tested up to` matches the WordPress version the CI matrix ran, that the readme changelog has a `= <version> =` section, that the WordPress.org listing assets exist, and that every shipped file carrying a third-party license header is listed in `docs/THIRD-PARTY.md`.

`verify-endpoints.py` probes Pro's license activation, license revalidation and update endpoints (URLs read from `LicenseManager::API_URL` and `Updater::API_URL`) with a deliberately invalid key, and fails unless each answers HTTP 200 with a JSON object in the shape the plugin reads. A Pro artifact is not accepted while it fails.

## 4. WordPress.org readiness

Install the exact archive into a clean disposable site and run the official Plugin Check:

```bash
python3 tests/integration/setup.py --private-mysql --wordpress-source /path/to/wordpress \
    --free-zip build/elemacy-<version>.zip
export ELEMACY_TEST_ENV=/tmp/elemacy-integration-<token>/environment.json
php tests/integration/run.php --exclude-group requires-acf-pro
curl --fail --silent --show-error --location https://downloads.wordpress.org/plugin/plugin-check.latest-stable.zip -o /tmp/plugin-check.zip
wp --path=/tmp/elemacy-integration-<token>/wordpress plugin install /tmp/plugin-check.zip --activate   # the site blocks outbound HTTP
wp --path=/tmp/elemacy-integration-<token>/wordpress plugin check elemacy --format=table
```

Zero error-level findings is the bar. Warnings that are accepted with a reason are listed in section 7 below; anything else is fixed before release.

From the first release after 1.0.0 on, the readme changelog names every user-visible change: limits, fail-closed behaviour, and any step an operator must take after upgrading. The 1.0.0 entry is a plain feature list: no earlier version exists to upgrade from.

## 5. Deploy

`.github/workflows/deploy-wordpress-plugin.yml` builds and verifies the archive on a tag, extracts it, and copies the extracted plugin (never the development tree) to the WordPress.org SVN trunk, with `wp-assets/` published as the listing assets. Pro is distributed through its own updater, not WordPress.org.

Only stable `vX.Y.Z` tags can deploy. The workflow runs the complete reusable CI workflow for that revision, then downloads and re-verifies the exact ZIP that passed its installation, integration and Plugin Check jobs. The Git tag, plugin header, runtime constant, readme stable tag and archive version must agree. The archive is checked against the production file allowlist; listing images, development sources, tests, docs and source maps are excluded.

Set repository secrets `WPORG_USERNAME` and `WPORG_PASSWORD` to the WordPress.org SVN credentials, and `WPORG_SVN_URL` to the repository root URL (for example, `https://plugins.svn.wordpress.org/elemacy`, without `/trunk`, `/tags` or `/assets`). Releases are serialized and committed atomically to `trunk`, a new `tags/X.Y.Z`, and the top-level SVN `assets` directory. Existing releases and version downgrades are rejected. Manual runs must select a version tag and default to a dry run with no SVN commit; uncheck `dry_run` to publish. Tag pushes publish automatically after all checks pass.

## 6. Operator notes for 1.0.0

- The inline notice shown in place of a loop that visitors cannot see appears only for users who can edit the page. Full-page caches serve guests, so the notice never reaches a cached page.
- A Loop Item template that is not published renders nothing for visitors.
- AJAX-paginated loops show at most 100 items per page; "0" or "unlimited" become 100.
- A popup or template whose saved rule, trigger or condition is unavailable (for example after deactivating an extension) is suspended rather than shown to everyone; its settings are kept for repair. The Theme Builder list marks such a template "Suspended" and names the condition.
- A Header, Footer, Single, Archive or Search template displays only where an inclusion condition matches; one with no condition is listed as "Not displayed". New templates start as Draft with their type's broadest condition (Entire Site, Singular, Post Type Archive, Search Results). A 404 template has no conditions and covers every not-found page.
- Deleting Free keeps its templates, popups and settings. Define `ELEMACY_REMOVE_ALL_DATA` as `true` in `wp-config.php` before deleting it to remove them.
- Deleting Pro keeps every popup's impression and conversion counts and its remaining options, and removes its license and temporary data. The same `ELEMACY_REMOVE_ALL_DATA` opt-in removes the counts too.
- With Elementor Pro's Theme Builder active, a location one of its templates applies to is left to Elementor Pro; Elemacy serves every other location. The Theme Builder screen says so.

## 7. Accepted Plugin Check warnings

Plugin Check run on the built archive (report kept beside the archive as `build/plugin-check.txt`): **0 errors, 95 warnings**, all in the groups below. Every group is accepted with a reason; a new warning outside these groups is fixed before release.

| Check | Where | Why it is accepted |
| --- | --- | --- |
| `WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound` | every `do_action`/`apply_filters` through `Core\Hooks` constants | The sniff cannot read a class constant. Every constant's value starts with `elemacy/` (see `docs/CONTRACTS.md`). |
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | `Elementor/Rendering/TemplateAssetsRegistrar.php`, `Loop/Items/PostLoopItem.php`, `Modules/DynamicTags/Tags/Base/PostContent.php`, `PostExcerpt.php`, `Widgets/NavMenu/MenuMarkup.php`, `Modules/ThemeBuilder/Documents/ChromeDocument.php` | The plugin applies hooks owned by others so their filters keep working: WordPress's `the_content`, `the_excerpt`, `the_title` and `nav_menu_*`, Elementor's `elementor/post/render` and `elementor/documents/register_controls`. It defines no hook of its own without the `elemacy/` prefix. |
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | `Loop/Items/WordPressPostScope.php` | It writes `$GLOBALS[$key]` only to restore WordPress's own `$post` / `$wp_query` from a snapshot it took itself; it introduces no global. |
| `WordPress.DB.DirectDatabaseQuery.*` | `uninstall.php`, `Core/Migrator.php`, `Core/Migrations/DatabaseLease.php`, `TemplateLibrary/ResolverCache.php`, `Modules/FormBuilder/Services/SubmissionRateLimiter.php` | Deliberate atomic operations that the options and meta APIs cannot express: compare-and-swap leases, bounded limiter buckets, generation-guarded cache writes and bounded uninstall batches. All prepared, all documented in `docs/STORAGE.md`. |
| `WordPress.DB.SlowDBQuery.slow_db_query_meta_query` | `TemplateLibrary/Services/BlockTemplateService.php`, `Modules/ThemeBuilder/Services/TemplateService.php`, `Modules/Popups/Services/PopupService.php`, `Modules/Popups/Support/PopupChoices.php` | Listings of the single library post type filtered by its type discriminator meta, paginated or capped (the popup picker at `PopupChoices::LIMIT`) and bounded by the library's size. Frontend lookups go through `ResolverCache`, not these queries. |
| `WordPress.DB.SlowDBQuery.slow_db_query_tax_query` | `Modules/ThemeBuilder/Services/ArchivePreviewQuery.php` | The editor preview of an Archive template must run the same query WordPress runs for a custom taxonomy archive, which is a single-term `tax_query`; categories and tags use `cat` / `tag_id` instead. It runs only in the editor preview, for the author, with the archive's normal page size. |
| `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_*` | `Loop/Sources/Posts/PostsQuery.php`, `Loop/Sources/Terms/TermsQuery.php`, `Loop/Sources/Users/UsersQuery.php`, `Widgets/SiteLogo/SiteLogo.php`, `Widgets/NavMenu/Controls/DropdownStyleSection.php` | "Exclude the current post / term / user" is an authored loop option with a single-id exclusion, and the public loop page size is capped at 100. In the widgets the `exclude` key is an Elementor group control's list of sub-controls to hide, not a query. |
| `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` | `Core/Elemacy.php` | Kept so the bundled `languages/` catalog also loads on installations that do not receive WordPress.org language packs. |
| `mismatched_plugin_name` | `readme.txt` | The listing title carries the product description ("Elemacy — All-in-One Elementor Addon…") while the plugin header stays "Elemacy" for the Plugins screen. Change both together if the listing name should match exactly. |

## 8. Post-review steps

1.0.0 is handed off on the `refactor/standardize-structure` branch of both repositories, pushed and **not merged**. In each repository that branch contains `release/1.0.0` and `fix/1-0-blockers`, so it is the only branch to review and merge. The owner reviews it manually and then performs every step below; nothing in CI merges, tags or deploys on its own.

### Evidence for the branch head

Each profile ran on a fresh disposable site (`tests/integration/setup.py --private-mysql`, WordPress 7.1.3, Elementor 4.3.5), every job alone and memory-capped.

| Check | Result |
| --- | --- |
| Fast harness | F 494/494, P 84/84 |
| phpcs | 0 errors in both repositories; the existing reserved-keyword and `Resource` class-name warnings only |
| Architecture check (rules 1–9, Pro beside Free) | 0 violations |
| Coverage inventory | `tests/coverage/check.py` passes; roadmaps current |
| Admin app | `npm run lint` and `npm run build` pass; every admin string is extractable (`wp.i18n.__`), `languages/*.pot` regenerated |
| Integration, free + Pro + ACF Pro | 676 tests, 0 failures, 38 skipped (real themes, Elementor Pro, Hello Elementor) |
| Integration, free-only + free ACF | 563 tests, 0 failures, 75 skipped (also Pro and ACF Pro) |
| Integration, free + Pro + ACF Pro + Elementor Pro + Hello Elementor | 676 tests, 0 failures, 27 skipped (real themes not installed) |
| Browser runners, free + Pro + ACF Pro | all 14 that apply pass, first run on a fresh site (popups 162, form submissions 35, nav menu 23, popup assets 13, loop load-more 13, loop pagination 11, current-query editor 11, current-query pagination 10, loop load-more editor 10, admin contract 7, editor template styles 7, Elementor lifecycle 7, nested repeater 5, builders 4) |
| Pro's own `run-popups-pro.mjs` | 65 pass, no page errors |
| Browser runners, Elementor Pro + Hello Elementor profile | nav-menu parity 9, topbar prerender 8, admin contract 7 |
| Browser runners, free-only | every free runner passes; Pro- and ACF-Pro-only runners skip by profile |
| Native journeys (Playwright) | free + Pro: 66/66; free-only (`--grep-invert @pro`): 47 passed, 1 skipped by profile |
| `verify-package.py` | F 585 files, P 98 files, all checks pass |
| Plugin Check on `build/elemacy-1.0.0.zip` | 0 errors, 95 warnings, all accepted in section 7; report at `build/plugin-check.txt` |

Archive checksums (`build/*.sha256`, produced by the package job):

```
b262943ed8ce33d15a324df15c0e42c99e3146bba0becdea108140d229402eda  elemacy-1.0.0.zip
1f82748b6d3bcdd27a9e55650be481c36cb32a62d0e8ba4e6a37124bbe29c5d5  elemacy-pro-1.0.0.zip
```

The `build/` directory is not committed and `docs/`, `.github/` and tests are not shipped, so these checksums hold for the branch head. Rebuild with `npm run build:zip` from `src-react/` and compare; a different checksum means a shipped file changed after the hand-off and the gate must run again.

### Owner steps

1. Review `refactor/standardize-structure` in both repositories (Free first). It is cut from `fix/1-0-blockers`, which is cut from `release/1.0.0`.
2. Merge Free's `refactor/standardize-structure` into `main`, then Pro's. Pro's CI pins the Free revision it was tested with (`ELEMACY_FREE_REVISION`); after merging Free, Pro's pin still names a commit on Free's history, so it resolves.
3. Confirm CI is green on both merge commits. The Free `package` job re-runs `verify-package.py` and Plugin Check and fails on any error-level finding.
4. Replace the generated listing images in `wp-assets/` with the designed ones if they are ready; the sizes in section 4 are fixed. This is the only content change expected between hand-off and release.
5. Tag the Free merge commit `v1.0.0` and push the tag. `deploy-wordpress-plugin.yml` triggers on `v*` tags, builds and verifies the archive again, and copies the extracted archive to WordPress.org trunk and `tags/1.0.0` with `wp-assets/` as the listing assets.
6. Confirm the WordPress.org listing shows 1.0.0 with the banner, icon and the 1.0.0 changelog, then install the listed plugin on a clean site and open one page of each builder.
7. Pro is not on WordPress.org; ship `build/elemacy-pro-1.0.0.zip` through its own updater after the Free listing is live.
8. Archive the OpenSpec changes once merged (`/opsx:archive standardize-project-structure`).
