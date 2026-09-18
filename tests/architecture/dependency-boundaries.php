<?php

/**
 * Architecture dependency boundary check (design.md D7, task 7.12).
 *
 * Scans this plugin's own source tree for `use` imports that cross one of
 * three forbidden directions, established across phases 1–7 of
 * harden-first-release-architecture:
 *
 *  - Core -> Modules\Widgets   (Core/Preview, Core/Documents must consume
 *    Loop/'s own contracts, never a concrete Widgets class — task 7.6)
 *  - Modules\Widgets -> Modules\DynamicTags (task 7.5)
 *  - Modules\DynamicTags -> Modules\Widgets (task 7.7)
 *
 * A handful of specific, already-documented exceptions remain — files still
 * referencing a Widgets-owned class no task in this phase named for moving
 * (`LoopDataSourceInterface`, `PublicLoopLimits`, `PlacementResolverRegistry`)
 * or a single deliberate `class_alias()`-ordering workaround
 * (see PublicAggregateSourceAdapter.php and
 * elemacy_class_alias_instanceof_hazard.md). Each is listed explicitly below
 * rather than silently excluded by a wildcard, so a *new* violation in an
 * already-exempt file is still caught.
 *
 * Run: php tests/architecture/dependency-boundaries.php
 */

$plugin_root = dirname(__DIR__, 2) . '/';

/**
 * @var array<int, array{label: string, scan_dir: string, forbidden_prefix: string}>
 */
$rules = [
    [
        'label' => 'Core -> Modules\\Widgets',
        'scan_dir' => 'src/Core/',
        'forbidden_prefix' => 'Elemacy\\Modules\\Widgets\\',
    ],
    [
        'label' => 'Modules\\Widgets -> Modules\\DynamicTags',
        'scan_dir' => 'src/Modules/Widgets/',
        'forbidden_prefix' => 'Elemacy\\Modules\\DynamicTags\\',
    ],
    [
        'label' => 'Modules\\DynamicTags -> Modules\\Widgets',
        'scan_dir' => 'src/Modules/DynamicTags/',
        'forbidden_prefix' => 'Elemacy\\Modules\\Widgets\\',
    ],
    [
        'label' => 'Loop -> Modules\\Widgets',
        'scan_dir' => 'src/Loop/',
        'forbidden_prefix' => 'Elemacy\\Modules\\Widgets\\',
    ],
];

/**
 * Explicit allowlist: relative file path => list of exact `use` lines that
 * file is permitted to keep. Anything matching a rule above that is not
 * listed here for its own file is reported as a violation.
 */
$allowlist = [
    'src/Core/Providers/CoreServiceProvider.php' => [
        // PublicRenderPolicy's own bind() factory (tasks 6.6/7.4) resolves
        // the shared placement-resolver singleton directly; no task in this
        // phase moved PlacementResolverRegistry out of Widgets.
        'use Elemacy\\Modules\\Widgets\\Services\\PlacementResolverRegistry;',
    ],
    'src/Loop/LoopDataSourceRegistry.php' => [
        // The aggregate source contract itself is narrowed (task 7.3) but
        // not moved — migrating every implementer onto SourceDefinition's
        // narrow contracts is tasks 7.9-7.11's own scope, not this file's.
        'use Elemacy\\Modules\\Widgets\\Contracts\\LoopDataSourceInterface;',
    ],
    'src/Loop/SourceDefinition.php' => [
        'use Elemacy\\Modules\\Widgets\\Contracts\\LoopDataSourceInterface;',
    ],
    'src/Loop/Adapters/AggregateSourceAdapter.php' => [
        'use Elemacy\\Modules\\Widgets\\Contracts\\LoopDataSourceInterface;',
    ],
    'src/Loop/Adapters/PublicAggregateSourceAdapter.php' => [
        // Deliberate: instanceof never autoloads an unresolved name, so this
        // adapter must implements the exact old path PublicRenderPolicy's
        // own consumers may still check against — see this file's own doc
        // comment and elemacy_class_alias_instanceof_hazard.md (task 7.3).
        'use Elemacy\\Modules\\Widgets\\Contracts\\PublicLoopSourcePolicyInterface;',
    ],
    'src/Loop/Pagination/PublicRenderPolicy.php' => [
        'use Elemacy\\Modules\\Widgets\\Contracts\\LoopDataSourceInterface;',
        'use Elemacy\\Modules\\Widgets\\Services\\PlacementResolverRegistry;',
        'use Elemacy\\Modules\\Widgets\\Support\\PublicLoopLimits;',
    ],
    'src/Loop/DataSources/PostsQuery.php' => [
        'use Elemacy\\Modules\\Widgets\\Support\\PublicLoopLimits;',
    ],
    'src/Loop/DataSources/TermsQuery.php' => [
        'use Elemacy\\Modules\\Widgets\\Support\\PublicLoopLimits;',
    ],
    'src/Loop/DataSources/UsersQuery.php' => [
        'use Elemacy\\Modules\\Widgets\\Support\\PublicLoopLimits;',
    ],
    'src/Loop/Pagination/Policies/PostsPolicy.php' => [
        'use Elemacy\\Modules\\Widgets\\Support\\PublicLoopLimits;',
    ],
    'src/Loop/Pagination/Policies/TermsPolicy.php' => [
        'use Elemacy\\Modules\\Widgets\\Support\\PublicLoopLimits;',
    ],
    'src/Loop/Pagination/Policies/UsersPolicy.php' => [
        'use Elemacy\\Modules\\Widgets\\Support\\PublicLoopLimits;',
    ],
    'src/Loop/Rendering/LoopRenderer.php' => [
        // Still pushes/pops the existing ownership-only stack (task 8.4's
        // own docblock) so nested Loop Grid placement validation keeps
        // working unchanged; task 8.6 replaces this once the render loop
        // itself moves onto LoopRenderer everywhere.
        'use Elemacy\\Modules\\Widgets\\Services\\LoopTemplateRenderStack;',
    ],
];

$violations = [];

foreach ($rules as $rule) {
    $dir = $plugin_root . $rule['scan_dir'];

    if (!is_dir($dir)) {
        fwrite(STDERR, "Scan directory missing, skipping: {$dir}\n");
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

    foreach ($iterator as $file_info) {
        if ('php' !== $file_info->getExtension()) {
            continue;
        }

        $relative_path = ltrim(str_replace($plugin_root, '', $file_info->getPathname()), '/');
        $lines = file($file_info->getPathname());

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (0 !== strpos($trimmed, 'use ' . $rule['forbidden_prefix'])) {
                continue;
            }

            $allowed = $allowlist[$relative_path] ?? [];

            if (in_array($trimmed, $allowed, true)) {
                continue;
            }

            $violations[] = sprintf('[%s] %s: %s', $rule['label'], $relative_path, $trimmed);
        }
    }
}

if ($violations) {
    fwrite(STDERR, "Architecture dependency boundary violations found:\n\n");

    foreach ($violations as $violation) {
        fwrite(STDERR, '  - ' . $violation . "\n");
    }

    fwrite(STDERR, "\nIf this import is a deliberate, documented exception, add it to \$allowlist in this script with a comment explaining why. Otherwise, fix the import.\n");

    exit(1);
}

echo "Architecture dependency boundaries: no forbidden imports found.\n";
