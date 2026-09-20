# Actual WordPress / Elementor integration tests

These scenarios use a disposable MySQL schema and an actual WordPress installation. They supplement `php tests/php/run.php`; they do not load its stubs.

Requirements: PHP with WordPress extensions, Python 3, MySQL client/server, WP-CLI, installed WordPress/Elementor source, and Composer development dependencies in the Elemacy checkout (`composer install`). PHPUnit 9.6 is locked in Free's development dependencies. The explicitly supplied database account needs permission to create a new schema. Prefer a dedicated local test account. Never pass an existing database name: provisioning generates one and refuses collisions.

From the Free plugin root, supply `ELEMACY_TEST_DB_USER`, `ELEMACY_TEST_DB_PASSWORD` and optionally `ELEMACY_TEST_DB_HOST` through your local environment/secret manager, then:

```bash
python3 tests/integration/setup.py --wordpress-source /path/to/wordpress --port 8897
# Use the exact environment.json path printed by setup:
export ELEMACY_TEST_ENV=/tmp/elemacy-integration-<generated-token>/environment.json
php tests/integration/run.php
# Or select a registered scenario:
php tests/integration/run.php document-lifecycle.php
```

For a separately running MySQL instance with networking disabled, set `ELEMACY_TEST_DB_HOST=localhost` and `ELEMACY_TEST_DB_SOCKET` to its Unix socket. This also isolates the server from a development account that cannot create test schemas.

If `mysqld` is installed locally, the provisioner can own the entire disposable database server without any existing database credentials:

```bash
python3 tests/integration/setup.py --private-mysql --wordpress-source /path/to/wordpress \
  --with-pro --acf-plugin /path/to/acf-pro
```

This creates a new MySQL datadir inside the mode-0700 environment directory, disables TCP and MySQL X networking, and uses an unpassworded root account accessible only through that directory's private Unix socket. The provisioner prints its exact `mysqladmin ... shutdown` command. Stop that instance before removing the environment directory. Temporary directories may be cleared between sessions; provision a new environment in that case instead of falling back to a development database.

Add `--with-pro` to activate the sibling Pro checkout. Add `--acf-plugin /path/to/advanced-custom-fields[-pro]` for an explicitly available ACF installation. Neither option overrides entitlement or pretends an unavailable ACF feature exists.

Provisioning copies WordPress core and the fixture theme, links selected plugin checkouts, and owns a separate uploads directory. It does not read development credentials/configuration or copy development data. A mode-0600 manifest in a mode-0700 temporary directory holds test credentials and salts. Database names, root identity and the generated wp-config hash are checked **before** WordPress boots. No environment variable falls back to the surrounding development site. The site blocks outbound email/HTTP, cron and software updates.

The site is retained for browser/repeated scenario runs. Fixture cleanup removes only objects created by that fixture. To dispose of the entire environment, stop its local PHP server, drop only the exact `elemacy_integration_<generated-token>` schema printed by setup, and remove its matching temporary directory. Never use the development schema for cleanup. Nothing under `tests/integration` belongs in release ZIPs.

Native scenario files contain PHPUnit test classes and are explicitly registered in `phpunit.xml.dist`. `IntegrationTestCase` owns native document fixture cleanup and current-user restoration through PHPUnit's lifecycle. `run.php` preserves the integration command and forwards PHPUnit options such as `--filter`, `--testdox` and `--log-junit /tmp/elemacy-junit.xml`. Failed/risky tests and warnings give a nonzero exit. New regressions must assert real behavior rather than replace WordPress/Elementor functions with stubs.

PHPUnit 9.6 preserves this project's PHP 7.4 compatibility and follows the [WordPress PHPUnit compatibility matrix](https://make.wordpress.org/core/handbook/references/phpunit-compatibility-and-wordpress-versions/). PHPUnit lists this branch in [life support](https://phpunit.de/supported-versions.html), which maintains compatibility with newer PHP versions. Revisit the major version when the plugin's PHP floor or WordPress testing contract changes. The framework is development-only; the existing fast stub runners remain independent of it.

## Real browser baseline

With the same `ELEMACY_TEST_ENV` exported and `tests/e2e` dependencies installed:

```bash
ELEMACY_CHROMIUM=/path/to/chromium node tests/e2e/run-builders.mjs
```

The runner refreshes test-only theme/MU-plugin fixtures in the guarded temporary root, creates a native WordPress page referencing a real Elementor library document, starts PHP on the configured loopback port, and opens Chromium. It checks document output, Elementor base assets, JavaScript exceptions, console errors, failed requests and HTTP error responses. It removes its documents and stops its server in `finally`. Results, network responses and server output are saved to `builders-report.json` beside `environment.json`; no credentials enter that report.

This initial heading fixture establishes native rendering and the browser harness. Interactive child-widget hydration and builder-specific asset deadlines require the later phase-3 scenarios. Google Fonts are disabled through Elementor's fixture-only filter to keep this local test independent of an external font service. The existing static popup runner remains the fast state-machine suite.

## Admin contract browser checks

With the guarded environment provisioned with Pro, run from Free:

```bash
ELEMACY_TEST_ENV=/tmp/elemacy-integration-<token>/environment.json \
ELEMACY_CHROMIUM=/path/to/chromium node tests/e2e/run-admin-contract.mjs
```

The runner creates and revokes its own short-lived admin session, exercises opposite bundle delivery orders and an incompatible contract, opens the condition editor, and checks library/popup screens and Pro license validation. An additional temporary cached-valid license status exercises the actual analytics fill; the previous status and the owned popup are restored/deleted in `finally`. It uses the existing license policy in the disposable database only, without contacting a license service. Reports/screenshots are retained beside the environment manifest. Build both admin applications first. Pro's bundle report must show zero React modules.

## Form submissions

Run `php tests/integration/run.php --filter FormSubmissionsTest` with `ELEMACY_TEST_ENV` set to the guarded manifest. Tests cover saved definitions, native private read checks, malformed fields/settings/nonces, authored mail actions, transport failure, signed library placement, and concurrent fixed-window limits. Mail is captured locally without contacting a transport.

Run `node tests/e2e/run-form-submissions.mjs` for ordinary page, Theme Builder header, and hydrated popup submissions, including server field errors and frontend recovery. `node tests/e2e/run-popup-assets.mjs` additionally checks popup assets, child-widget hydration, submission acceptance, and reopening. Set `ELEMACY_CHROMIUM` if Chromium is not in Playwright's default cache. Run browser and database suites sequentially against each disposable environment. The reports are retained beside its manifest.

## Editor canvas and template styles

Run `node tests/e2e/run-editor-template-styles.mjs` to open a page in the real Elementor editor whose Theme Builder header and footer are authored with atomic elements, add atomic elements to the page, and check the header and footer keep their computed styles. It switches the disposable site to the fixture theme and restores the previous theme with its own fixture documents in `finally`.

## Native uninstall lifecycle

Use a disposable site only. With `ELEMACY_TEST_ENV` pointing to its manifest, run:

```bash
wp --path=/tmp/elemacy-integration-<token>/wordpress --skip-plugins --skip-themes eval-file tests/integration/fixtures/uninstall-current-site.php free
wp --path=/tmp/elemacy-integration-<token>/wordpress --skip-plugins --skip-themes eval-file tests/integration/fixtures/uninstall-current-site.php pro
```

These intentionally execute uninstall against the guarded disposable database, delete plugin-owned fixtures/settings, and verify native related-object cleanup, caches, ownership, and batches exceeding 100 records. Neither plugin runtime is loaded. Provision a separate network with `setup.py --multisite` and run `fixtures/uninstall-network.php` through the same WP-CLI command for 105 native sites, injected failure, continuation, retry and context restoration. Its owned sites are removed in `finally`; a temporary ownership manifest supports diagnosis after process interruption.
