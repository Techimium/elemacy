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

if ($violations) {
    fwrite(STDERR, "Architecture check failed:\n\n");

    foreach ($violations as $violation) {
        fwrite(STDERR, '  - ' . $violation . "\n");
    }

    fwrite(STDERR, "\nMove shared code into a shared layer, reach another module through a contract or hook, go through src/Elementor for Elementor APIs, keep planning references in docs/, and migrate stored values you rename. There is no allowlist.\n");

    exit(1);
}

echo "Architecture check: layering, Elementor boundary, planning-reference and persisted-identifier rules all pass.\n";
