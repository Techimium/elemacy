#!/usr/bin/env python3
"""Provision a fresh local test site. Never import a development wp-config.php."""

import argparse
import hashlib
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess
import tempfile
import zipfile
import stat


def php_string(value):
    return "'" + str(value).replace("\\", "\\\\").replace("'", "\\'") + "'"


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--wordpress-source", type=Path, required=True)
    parser.add_argument("--port", type=int, default=8897)
    parser.add_argument("--with-pro", action="store_true")
    parser.add_argument("--free-zip", type=Path, help="Install this exact Free archive instead of linking the checkout.")
    parser.add_argument("--pro-zip", type=Path, help="Install this exact Pro archive; requires --with-pro and --free-zip.")
    parser.add_argument("--multisite", action="store_true", help="Provision a disposable subdirectory network for lifecycle tests.")
    parser.add_argument("--acf-plugin", type=Path)
    parser.add_argument("--elementor-pro-plugin", type=Path,
                        help="Optional local Elementor Pro directory, for the Nav Menu parity and coexistence checks.")
    parser.add_argument("--hello-elementor-theme", type=Path,
                         help="Optional local Hello Elementor theme checkout, for its own compatibility-adapter fixtures.")
    parser.add_argument("--theme", type=Path, action="append", default=[],
                        help="A real theme directory to install under its own name (repeatable), e.g. a classic theme "
                             "the native-chrome replacement cases then also run against.")
    parser.add_argument("--private-mysql", action="store_true", help="Start an isolated socket-only mysqld under the temporary directory.")
    args = parser.parse_args()
    if not 1024 <= args.port <= 65535:
        parser.error("Use an unprivileged local port (1024–65535).")
    if args.pro_zip and (not args.with_pro or not args.free_zip):
        parser.error("--pro-zip requires --with-pro and --free-zip.")
    source = args.wordpress_source.resolve()
    if not (source / "wp-includes/version.php").is_file():
        parser.error("The WordPress source directory is invalid.")
    if not args.private_mysql and not os.environ.get("ELEMACY_TEST_DB_USER"):
        parser.error("Set ELEMACY_TEST_DB_USER and ELEMACY_TEST_DB_PASSWORD explicitly.")

    harness = Path(__file__).resolve().parent
    free = harness.parent.parent
    plugins = {"elemacy": free, "elementor": source / "wp-content/plugins/elementor"}
    if args.with_pro:
        plugins["elemacy-pro"] = free.parent / "elemacy-pro"
    if args.acf_plugin:
        acf = args.acf_plugin.resolve()
        if not (acf / "acf.php").is_file():
            parser.error("--acf-plugin must point to an ACF plugin directory.")
        plugins[acf.name] = acf
    if args.elementor_pro_plugin:
        elementor_pro = args.elementor_pro_plugin.resolve()
        if elementor_pro.name != "elementor-pro" or not (elementor_pro / "elementor-pro.php").is_file():
            parser.error("--elementor-pro-plugin must point to an elementor-pro plugin directory.")
        plugins["elementor-pro"] = elementor_pro
    if any(not directory.is_dir() for directory in plugins.values()):
        parser.error("An explicitly selected plugin directory is absent.")
    hello_theme = None
    if args.hello_elementor_theme:
        hello_theme = args.hello_elementor_theme.resolve()
        if not (hello_theme / "style.css").is_file():
            parser.error("--hello-elementor-theme must point to a theme directory (with style.css).")
    fixture_themes = {"elemacy-fixture", "astra", "theme-no-body-open", "theme-location-wrapper", "theme-chrome-assets",
                      "theme-generic-chrome", "theme-unreadable-header", "theme-block-custom-parts", "theme-compat-stub", "hello-elementor", "bb-theme"}
    real_themes = [theme.resolve() for theme in args.theme]
    for theme in real_themes:
        if not (theme / "style.css").is_file():
            parser.error("--theme must point to a theme directory (with style.css): " + str(theme))
        if theme.name in fixture_themes:
            parser.error("--theme " + theme.name + " would replace a fixture theme of the same name.")
    # Bundled with any real WordPress core source, so this needs no separate
    # flag; block-theme fixtures degrade gracefully (skip, not error) if a
    # --wordpress-source ever ships without it.
    block_theme = source / "wp-content/themes/twentytwentyfive"
    if not block_theme.is_dir():
        block_theme = None

    token = secrets.token_hex(8)
    parent = Path(tempfile.gettempdir()) / ("elemacy-integration-" + token)
    parent.mkdir(mode=0o700)  # Refuse collisions; never reuse or erase an existing directory.
    root = parent / "wordpress"
    root.mkdir()
    database = "elemacy_integration_" + token
    private_socket = None
    if args.private_mysql:
        datadir = parent / 'mysql-data'
        datadir.mkdir()
        private_socket = str(parent / 'mysql.sock')
        mysql_common = ['mysqld', '--no-defaults', '--datadir=' + str(datadir),
                        '--innodb-buffer-pool-size=64M', '--innodb-redo-log-capacity=64M']
        subprocess.run(mysql_common + ['--initialize-insecure', '--log-error=' + str(parent / 'mysql-initialize.log')], check=True)
        subprocess.run(mysql_common + [
            '--daemonize', '--skip-networking', '--mysqlx=0', '--skip-log-bin',
            '--socket=' + private_socket, '--pid-file=' + str(parent / 'mysql.pid'),
            '--log-error=' + str(parent / 'mysql.log'),
        ], check=True)
    environment = {
        "purpose": "elemacy-disposable-integration-v1",
        "token": token,
        "root": str(root),
        "database": database,
        "db_host": 'localhost' if private_socket else os.environ.get("ELEMACY_TEST_DB_HOST", "localhost"),
        "db_user": 'root' if private_socket else os.environ["ELEMACY_TEST_DB_USER"],
        "db_password": '' if private_socket else os.environ.get("ELEMACY_TEST_DB_PASSWORD", ""),
        "url": "http://127.0.0.1:" + str(args.port),
        "salts": {key: secrets.token_hex(32) for key in (
            "AUTH_KEY", "SECURE_AUTH_KEY", "LOGGED_IN_KEY", "NONCE_KEY",
            "AUTH_SALT", "SECURE_AUTH_SALT", "LOGGED_IN_SALT", "NONCE_SALT",
        )},
    }
    mysql_host = environment['db_host']
    mysql_socket = private_socket or os.environ.get('ELEMACY_TEST_DB_SOCKET')
    if mysql_socket:
        if mysql_host != 'localhost' or not Path(mysql_socket).is_socket():
            parser.error('ELEMACY_TEST_DB_SOCKET requires localhost and an existing Unix socket.')
        environment['db_host'] = 'localhost:' + mysql_socket

    # Use CREATE without IF NOT EXISTS. Never install into an existing schema.
    # The temporary MySQL option file avoids password exposure in process args.
    defaults = parent / "mysql.cnf"
    def mysql_quote(value):
        return '"' + value.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n").replace("\r", "\\r") + '"'
    defaults.touch(mode=0o600)
    connection = {'host': mysql_host, 'user': environment['db_user'], 'password': environment['db_password']}
    if mysql_socket:
        connection['socket'] = mysql_socket
    defaults.write_text("[client]\n" + "\n".join(
        key + "=" + mysql_quote(value) for key, value in connection.items()
    ) + "\n")
    try:
        subprocess.run(["mysql", "--defaults-extra-file=" + str(defaults), "--execute",
                        "CREATE DATABASE `" + database + "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"], check=True)
    finally:
        defaults.unlink()

    # Copy core PHP files: symlinking wp-load.php would resolve __DIR__ to the
    # development root. Own wp-content means generated CSS/uploads stay isolated.
    for filename in source.glob("*.php"):
        if filename.name not in ("wp-config.php", "wp-config-sample.php"):
            shutil.copy2(filename, root / filename.name)
    for directory in ("wp-admin", "wp-includes"):
        shutil.copytree(source / directory, root / directory)
    content = root / "wp-content"
    (content / "plugins").mkdir(parents=True)
    (content / "uploads").mkdir()
    archives = {"elemacy": args.free_zip, "elemacy-pro": args.pro_zip}
    environment["packages"] = {}
    for name, path in plugins.items():
        archive_path = archives.get(name)
        if archive_path:
            archive_path = archive_path.resolve()
            with zipfile.ZipFile(archive_path) as archive:
                for entry in archive.infolist():
                    parts = Path(entry.filename).parts
                    if (not parts or parts[0] != name or ".." in parts
                            or entry.filename.startswith("/") or "\\" in entry.filename
                            or stat.S_ISLNK(entry.external_attr >> 16)):
                        parser.error("Unsafe package member: " + entry.filename)
                archive.extractall(content / "plugins")
            if not (content / "plugins" / name / (name + ".php")).is_file():
                parser.error("Package lacks its plugin bootstrap: " + name)
            environment["packages"][name] = {"archive": str(archive_path), "sha256": hashlib.sha256(archive_path.read_bytes()).hexdigest()}
        else:
            (content / "plugins" / name).symlink_to(path, target_is_directory=True)
    shutil.copytree(harness / "fixtures/theme", content / "themes/elemacy-fixture")
    # "astra" must match that exact slug so CompatibilityManager's real
    # selection logic routes to the real AstraCompatibility adapter — this
    # fixture replicates only Astra's public astra_header/astra_footer hook
    # contract, not its actual theme.
    for name in ("astra", "theme-no-body-open", "theme-location-wrapper", "theme-chrome-assets",
                 "theme-generic-chrome", "theme-unreadable-header", "theme-block-custom-parts", "theme-compat-stub", "bb-theme"):
        shutil.copytree(harness / "fixtures" / name, content / "themes" / name)
    if hello_theme:
        shutil.copytree(hello_theme, content / "themes/hello-elementor")
    for theme in real_themes:
        shutil.copytree(theme, content / "themes" / theme.name)
    if block_theme:
        shutil.copytree(block_theme, content / "themes" / block_theme.name)
    shutil.copytree(harness / "fixtures/mu-plugins", content / "mu-plugins")

    config = """<?php
require_once HARNESS_ENVIRONMENT;
$elemacy_environment = elemacy_test_environment(__DIR__ . '/../environment.json');
define('DB_NAME', $elemacy_environment['database']);
define('DB_USER', $elemacy_environment['db_user']);
define('DB_PASSWORD', $elemacy_environment['db_password']);
define('DB_HOST', $elemacy_environment['db_host']);
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');
foreach ($elemacy_environment['salts'] as $key => $value) {
    define($key, $value);
}
define('WP_HOME', $elemacy_environment['url']);
define('WP_SITEURL', $elemacy_environment['url']);
define('ELEMACY_INTEGRATION_TOKEN', $elemacy_environment['token']);
define('WP_ENVIRONMENT_TYPE', 'local');
define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_DEBUG_LOG', __DIR__ . '/../debug.log');
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('DISALLOW_FILE_MODS', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);
define('WP_ACCESSIBLE_HOSTS', '127.0.0.1');
$table_prefix = 'etest_';
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}
require_once ABSPATH . 'wp-settings.php';
""".replace("HARNESS_ENVIRONMENT", php_string(harness / "environment.php"))
    (root / "wp-config.php").write_text(config)
    environment["wp_config_sha256"] = hashlib.sha256(config.encode()).hexdigest()
    manifest = parent / "environment.json"
    manifest.touch(mode=0o600)
    manifest.write_text(json.dumps(environment, indent=2) + "\n")

    def wp(*arguments, **kwargs):
        subprocess.run(["wp", "--path=" + str(root), *arguments], check=True, **kwargs)

    # The generated admin password is passed over stdin, never logged or stored.
    wp("core", "install", "--url=" + environment["url"], "--title=Elemacy integration",
       "--admin_user=elemacy_test_admin", "--admin_email=admin@example.invalid", "--skip-email",
       "--prompt=admin_password", input=secrets.token_urlsafe(32) + "\n", text=True, stdout=subprocess.DEVNULL)
    if args.multisite:
        wp("core", "multisite-convert", "--title=Elemacy integration network", "--skip-config")
        network_config = "\n".join([
            "define('MULTISITE', true);", "define('SUBDOMAIN_INSTALL', false);",
            "define('DOMAIN_CURRENT_SITE', '127.0.0.1:" + str(args.port) + "');",
            "define('PATH_CURRENT_SITE', '/');", "define('SITE_ID_CURRENT_SITE', 1);",
            "define('BLOG_ID_CURRENT_SITE', 1);",
        ])
        config = config.replace("$table_prefix =", network_config + "\n$table_prefix =")
        (root / "wp-config.php").write_text(config)
        environment["wp_config_sha256"] = hashlib.sha256(config.encode()).hexdigest()
        environment["multisite"] = True
        manifest.write_text(json.dumps(environment, indent=2) + "\n")
    wp("option", "update", "elemacy_integration_environment", token)
    wp("theme", "activate", "elemacy-fixture")
    wp("plugin", "activate", "elementor", "elemacy")
    if args.acf_plugin:
        wp("plugin", "activate", args.acf_plugin.resolve().name)
    if args.with_pro:
        wp("plugin", "activate", "elemacy-pro")
    if args.elementor_pro_plugin:
        wp("plugin", "activate", "elementor-pro")
    print("ELEMACY_TEST_ENV=" + str(manifest))
    print("Disposable schema: " + database)
    print("No development tables or configuration were imported.")
    print("Hello Elementor theme fixtures: " + ("available" if hello_theme else "skipped (no --hello-elementor-theme)"))
    print("Block theme fixtures: " + ("available (" + block_theme.name + ")" if block_theme else "skipped (none bundled with --wordpress-source)"))
    print("Elementor Pro: " + ("active" if args.elementor_pro_plugin else "absent (pass --elementor-pro-plugin)"))
    print("Real themes: " + (", ".join(theme.name for theme in real_themes) or "none (pass --theme)"))
    if private_socket:
        print('Private MySQL socket: ' + private_socket)
        print('Stop this instance with: mysqladmin --no-defaults --socket=' + private_socket + ' --user=root shutdown')


if __name__ == "__main__":
    main()
