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

`.github/workflows/deploy-wordpress-plugin.yml` builds and verifies the archive on a tag, extracts it, and copies the extracted plugin (never the development tree) to the WordPress.org SVN trunk, with `.wordpress-org/` published as the listing assets. Pro is distributed through its own updater, not WordPress.org.

## 6. Operator notes for 1.0.0

- The inline notice shown in place of a loop that visitors cannot see appears only for users who can edit the page. Full-page caches serve guests, so the notice never reaches a cached page.
- A Loop Item template that is not published renders nothing for visitors.
- AJAX-paginated loops show at most 100 items per page; "0" or "unlimited" become 100.
- A popup or template whose saved rule, trigger or condition is unavailable (for example after deactivating an extension) is suspended rather than shown to everyone; its settings are kept for repair.

## 7. Accepted Plugin Check warnings

Plugin Check 1.0.0 run on the built archive (report kept beside the archive as `build/plugin-check.txt`): **0 errors, 88 warnings**, all in the groups below. Every group is accepted with a reason; a new warning outside these groups is fixed before release.

| Check | Where | Why it is accepted |
| --- | --- | --- |
| `WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound` | every `do_action`/`apply_filters` through `Core\Hooks` constants | The sniff cannot read a class constant. Every constant's value starts with `elemacy/` (see `docs/CONTRACTS.md`). |
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | `Elementor/Rendering/TemplateAssetsRegistrar.php`, `Loop/Items/PostLoopItem.php`, `Modules/DynamicTags/Tags/Base/PostContent.php`, `PostExcerpt.php`, `Modules/Widgets/Walkers/NavMenuWalker.php` | The plugin applies hooks owned by others so their filters keep working: WordPress's `the_content`, `the_excerpt`, `the_title` and `nav_menu_*`, Elementor's `elementor/post/render`. It defines no hook of its own without the `elemacy/` prefix. |
| `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | `Loop/Context/WordPressPostScope.php`, `Modules/Widgets/Forms/Services/PublishedFormDefinitionResolver.php` | Both write `$GLOBALS[$key]` only to restore WordPress's own `$post` / `$wp_query` from a snapshot they took themselves; neither introduces a global. |
| `WordPress.DB.DirectDatabaseQuery.*` | `uninstall.php`, `Core/Migrator.php`, `Core/Migrations/DatabaseLease.php`, `TemplateLibrary/ResolverCache.php`, `Modules/Widgets/Forms/Services/SubmissionRateLimiter.php` | Deliberate atomic operations that the options and meta APIs cannot express: compare-and-swap leases, bounded limiter buckets, generation-guarded cache writes and bounded uninstall batches. All prepared, all documented in `docs/STORAGE.md`. |
| `WordPress.DB.SlowDBQuery.slow_db_query_meta_query` | `TemplateLibrary/Services/BlockTemplateService.php`, `Modules/ThemeBuilder/Services/TemplateService.php`, `Modules/Popups/Services/PopupService.php` | Listings of the single library post type filtered by its type discriminator meta, paginated and bounded by the library's size. Frontend lookups go through `ResolverCache`, not these queries. |
| `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_*` | `Loop/Sources/PostsQuery.php`, `TermsQuery.php`, `UsersQuery.php`, `Modules/Widgets/Widgets/SiteLogo.php` | "Exclude the current post / term / user" is an authored loop option with a single-id exclusion, and the public loop page size is capped at 100. |
| `PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound` | `Core/Elemacy.php` | Kept so the bundled `languages/` catalog also loads on installations that do not receive WordPress.org language packs. |
| `mismatched_plugin_name` | `readme.txt` | The listing title carries the product description ("Elemacy — All-in-One Elementor Addon…") while the plugin header stays "Elemacy" for the Plugins screen. Change both together if the listing name should match exactly. |

## 8. Post-review steps

The 1.0.0 cleanup is handed off on the `release/1.0.0` branch of both repositories, pushed and **not merged**. The owner reviews it manually and then performs every step below; nothing in CI merges, tags or deploys on its own.

### Evidence for the branch head

| Check | Result |
| --- | --- |
| Fast harness | F 274/274, P 65/65 |
| phpcs | clean in both repositories |
| Architecture check | 0 violations |
| Integration suite (disposable site, WP 7.1 pins, Elementor 4.2.3, free ACF) | 210 tests, 0 failures, 6 skipped (ACF Pro group) |
| Browser suites (builders, loop pagination, popup assets, form submissions) | 4 + 7 + 13 + 13 pass |
| Dedicated theme adapters on real installs (2026-09-27, after the generic path was rewritten) | Astra 4.14.0, GeneratePress 3.6.1, Hello Elementor 3.5.1: header and footer replaced through each adapter, each Elemacy template rendered once, none of the theme's own header/footer markup left (Astra's remaining matches are CSS selectors only; GeneratePress keeps its `.site-footer` wrapper as the footer position), one document shell, each third-party head/body-open/footer callback run once. OceanWP, Kadence, Neve, Blocksy and Storefront are on WordPress.org but were not installed; Genesis and the Beaver Builder theme are commercial. |
| Installed archive pair on Elementor 4.2.3 and 3.35.9 | 210 tests, 0 failures each; all four browser suites pass on both |
| `verify-package.py` | F 451 files, P 78 files, all checks pass |
| Plugin Check 1.0.0 on `build/elemacy-1.0.0.zip` | 0 errors, 88 warnings, all accepted in section 7; report at `build/plugin-check.txt` |

Archive checksums (`build/*.sha256`, produced by the package job):

```
7feb964b15cf9e8f5cc25706aa3f23df878c9174f2d72f5fcdfae0b5fcad0c9f  elemacy-1.0.0.zip
dedcbbd0183a41e184509e87c173e75c75488419675d19b4e499a48d67e66678  elemacy-pro-1.0.0.zip
```

The `build/` directory is not committed. Rebuild with `npm run build:zip` from `src-react/` and compare the checksum; a different checksum means a shipped file changed after the hand-off and the gate must run again.

### Owner steps

1. Review `release/1.0.0` in both repositories. The Free branch is cut from `feat/template-condition`, the Pro branch from `feat/animation`.
2. Merge each `release/1.0.0` into its current branch, then into `main` the usual way. Do not squash away the two commits; they separate the hardening from the cleanup.
3. Confirm CI is green on the merge commit. The Free `package` job re-runs `verify-package.py` and Plugin Check and fails on any error-level finding.
4. Replace the generated listing images in `.wordpress-org/` with the designed ones if they are ready; the sizes in section 4 are fixed. This is the only content change expected between hand-off and release.
5. Tag the Free merge commit `v1.0.0` and push the tag. `deploy-wordpress-plugin.yml` triggers on `v*` tags, builds and verifies the archive again, and copies the extracted archive to WordPress.org trunk and `tags/1.0.0` with `.wordpress-org/` as the listing assets.
6. Confirm the WordPress.org listing shows 1.0.0 with the banner, icon and the 1.0.0 changelog, then install the listed plugin on a clean site and open one page of each builder.
7. Pro is not on WordPress.org; ship `build/elemacy-pro-1.0.0.zip` through its own updater after the Free listing is live.
8. Archive the OpenSpec change `clean-architecture-for-first-release` once merged (`/opsx:archive`).
