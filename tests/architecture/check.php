<?php

/**
 * Architecture check.
 *
 * 1. Shared layers (Core, Http, Elementor, Loop, Conditions, TemplateLibrary,
 *    Integrations, Support) never import a feature module, and a feature
 *    module never imports another module's classes; modules cooperate only
 *    through the shared layers' contracts and the hooks in Core\Hooks.
 *    Config/ is the composition root's declaration of which modules exist and
 *    may name them.
 * 2. Only src/Elementor/ and the Elementor-instantiated classes inside
 *    src/Modules/ reference Elementor's own PHP namespace.
 * 3. Production source and shipped assets never reference planning
 *    artifacts (design documents, task numbers, change names).
 * 4. Every persisted identifier in persisted-identifiers.php is still
 *    declared where it says, unless a migration still names it: a rename
 *    ships with the Core\Migrator step that rewrites the stored value.
 *    ELEMACY_PERSISTED_IDENTIFIERS points at another list (used to plant a
 *    rename).
 *
 * There is no allowlist; an exception is a violation.
 *
 * Run: php tests/architecture/check.php
 */

$plugin_root = dirname(__DIR__, 2) . '/';
$shared_layers = ['Core', 'Http', 'Elementor', 'Loop', 'Conditions', 'TemplateLibrary', 'Integrations', 'Support'];
$violations = [];

$php_files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_root . 'src', FilesystemIterator::SKIP_DOTS)
);

foreach ($php_files as $file_info) {
    if ('php' !== $file_info->getExtension()) {
        continue;
    }

    $relative_path = ltrim(str_replace($plugin_root, '', $file_info->getPathname()), '/');
    $segments = explode('/', substr($relative_path, strlen('src/')));
    $layer = $segments[0];
    $module = 'Modules' === $layer ? ($segments[1] ?? '') : null;
    $may_use_elementor = 'Elementor' === $layer || null !== $module;

    foreach (file($file_info->getPathname()) as $number => $line) {
        $trimmed = trim($line);

        if (preg_match('/^use Elemacy\\\\Modules\\\\([A-Za-z0-9_]+)\\\\/', $trimmed, $match)) {
            if (null === $module && in_array($layer, $shared_layers, true)) {
                $violations[] = sprintf('[%s -> Modules\\%s] %s: %s', $layer, $match[1], $relative_path, $trimmed);
            } elseif (null !== $module && $match[1] !== $module) {
                $violations[] = sprintf('[Modules\\%s -> Modules\\%s] %s: %s', $module, $match[1], $relative_path, $trimmed);
            }
        }

        if (!$may_use_elementor && preg_match('/(?<![A-Za-z\\\\])\\\\?Elementor\\\\/', $line)) {
            $violations[] = sprintf('[Elementor API outside src/Elementor and src/Modules] %s:%d: %s', $relative_path, $number + 1, $trimmed);
        }
    }
}

$planning = '/design\.md|tasks\.md|\btask \d+\.\d+|openspec|harden-first-release-architecture|clean-architecture-for-first-release/i';

foreach (['src', 'assets', 'src-react/src'] as $tree) {
    if (!is_dir($plugin_root . $tree)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($plugin_root . $tree, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file_info) {
        if (!in_array($file_info->getExtension(), ['php', 'js', 'ts', 'tsx', 'css', 'json', 'html'], true)) {
            continue;
        }

        $relative_path = ltrim(str_replace($plugin_root, '', $file_info->getPathname()), '/');

        foreach (file($file_info->getPathname()) as $number => $line) {
            if (preg_match($planning, $line)) {
                $violations[] = sprintf('[planning reference] %s:%d: %s', $relative_path, $number + 1, trim($line));
            }
        }
    }
}

$without_comments = static function (string $path): string {
    $code = '';

    foreach (token_get_all((string) file_get_contents($path)) as $token) {
        if (!is_array($token)) {
            $code .= $token;
        } elseif (T_COMMENT !== $token[0] && T_DOC_COMMENT !== $token[0]) {
            $code .= $token[1];
        }
    }

    return $code;
};

$migrations = $without_comments($plugin_root . 'src/Core/Migrator.php');
foreach (glob($plugin_root . 'src/Core/Migrations/*.php') ?: [] as $migration) {
    $migrations .= $without_comments($migration);
}

$identifiers = require (getenv('ELEMACY_PERSISTED_IDENTIFIERS') ?: __DIR__ . '/persisted-identifiers.php');

foreach ($identifiers as $identifier) {
    $path = $plugin_root . $identifier['file'];
    $quoted = "'" . $identifier['id'] . "'";
    $declared = is_file($path)
        && 1 === preg_match($identifier['declaration'] ?? '/' . preg_quote($quoted, '/') . '/', $without_comments($path));

    if (!$declared && false === strpos($migrations, $quoted)) {
        $violations[] = sprintf('[persisted identifier] %s is no longer declared in %s and no migration names it; a rename ships a Core\\Migrator step (docs/CONTRACTS.md § Permanent identifiers)', $quoted, $identifier['file']);
    }
}

// Saved content names a dynamic tag by its registered name, and Elementor's
// tag registry is keyed by that name alone: an unprefixed built-in tag can
// replace (or be replaced by) another plugin's tag of the same name.
$tag_files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($plugin_root . 'src/Modules/DynamicTags/Tags', FilesystemIterator::SKIP_DOTS)
);

foreach ($tag_files as $file_info) {
    if ('php' !== $file_info->getExtension()) {
        continue;
    }

    $relative_path = ltrim(str_replace($plugin_root, '', $file_info->getPathname()), '/');
    $code = $without_comments($file_info->getPathname());

    if (!preg_match('/function\s+get_name\s*\(/', $code)) {
        continue;
    }

    if (!preg_match('/function\s+get_name\s*\([^)]*\)[^{]*\{\s*return\s+\'([^\']*)\'\s*;/', $code, $match)) {
        $violations[] = sprintf('[dynamic tag name] %s: get_name() must return a string literal', $relative_path);
    } elseif (0 !== strpos($match[1], 'elemacy-')) {
        $violations[] = sprintf("[dynamic tag name] %s: '%s' must start with 'elemacy-' so it never shares a registered name with another plugin's tag", $relative_path, $match[1]);
    }
}

// The plugin declares PHP 7.4, where these tokens do not parse. PHPCS's
// PHPCompatibility 9.x predates PHP 8 and cannot see them; CI's PHP 7.4
// `php -l` stays the authority, this is the fast local net. On a 7.4 runner
// the constants are undefined and the scan is a no-op.
$php8_tokens = [];
foreach (['T_NULLSAFE_OBJECT_OPERATOR', 'T_MATCH', 'T_ATTRIBUTE', 'T_ENUM', 'T_READONLY'] as $token_name) {
    if (defined($token_name)) {
        $php8_tokens[constant($token_name)] = $token_name;
    }
}

$shipped_php = [$plugin_root . 'elemacy.php', $plugin_root . 'uninstall.php'];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin_root . 'src', FilesystemIterator::SKIP_DOTS)) as $file_info) {
    if ('php' === $file_info->getExtension()) {
        $shipped_php[] = $file_info->getPathname();
    }
}

foreach ($php8_tokens ? $shipped_php : [] as $path) {
    $relative_path = ltrim(str_replace($plugin_root, '', $path), '/');

    // TOKEN_PARSE: a keyword used as a name (a method called match()) is a
    // plain identifier, which 7.4 accepts, rather than the PHP 8 construct.
    foreach (token_get_all((string) file_get_contents($path), TOKEN_PARSE) as $token) {
        if (is_array($token) && isset($php8_tokens[$token[0]])) {
            $violations[] = sprintf('[PHP 7.4 syntax floor] %s:%d: %s does not parse on PHP 7.4', $relative_path, $token[2], $php8_tokens[$token[0]]);
        }
    }
}

if ($violations) {
    fwrite(STDERR, "Architecture check failed:\n\n");

    foreach ($violations as $violation) {
        fwrite(STDERR, '  - ' . $violation . "\n");
    }

    fwrite(STDERR, "\nMove shared code into a shared layer, reach another module through a contract or hook, go through src/Elementor for Elementor APIs, keep planning references in docs/, and migrate stored values you rename. There is no allowlist.\n");

    exit(1);
}

echo "Architecture check: layering, Elementor boundary, planning-reference, persisted-identifier, dynamic-tag-name and PHP 7.4 syntax-floor rules all pass.\n";
