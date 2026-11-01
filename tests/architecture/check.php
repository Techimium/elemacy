<?php

/**
 * Architecture check.
 *
 * 1. Shared layers (Core, Http, Elementor, Widgets, Loop, Conditions,
 *    TemplateLibrary, Integrations, Support) never import a feature module, and a feature
 *    module never imports another module's classes; modules cooperate only
 *    through the shared layers' contracts and the hooks in Core\Hooks.
 *    Config/ is the composition root's declaration of which modules exist and
 *    may name them.
 * 2. Only src/Elementor/, src/Widgets/ and the Elementor-instantiated classes
 *    inside src/Modules/ reference Elementor's own PHP namespace. In
 *    Modules/FormBuilder only Widgets/ and Elementor/ may: the forms engine
 *    there stays builder-neutral.
 * 3. Production source and shipped assets never reference planning
 *    artifacts (design documents, task numbers, change names).
 * 4. Every persisted identifier in persisted-identifiers.php is still
 *    declared where it says, unless a migration still names it: a rename
 *    ships with the Core\Migrator step that rewrites the stored value.
 *    ELEMACY_PERSISTED_IDENTIFIERS points at another list (used to plant a
 *    rename).
 *
 * 5. No retired public popup or form name (content hooks, JavaScript globals,
 *    events, the old form endpoint and placement token) appears in Free's or
 *    Pro's shipped source: the 1.0 names are final and
 *    nothing keeps an alias. Pro is checked when its checkout sits beside
 *    this one, as it does in development and CI.
 *
 * 6. Every REST route and REST URL either plugin hands out is versioned
 *    (`elemacy/v1/...`): no unversioned namespace, `rest_url('elemacy/...')`
 *    or `/elemacy/...` route literal in shipped source.
 * 7. Every Elementor widget type Free registers is `elemacy-` plus lowercase
 *    words joined by hyphens: no underscore, so one naming rule holds for
 *    every widget saved in `_elementor_data`.
 * 8. The PHP extension contract (docs/CONTRACTS.md § PHP extension
 *    contract): every Free class, interface and trait carries exactly one of
 *    `@api` and `@internal`; every interface lives in a Contracts/ folder and
 *    ends in `Interface`; no `@api` interface's signature names an
 *    `@internal` Free class; and Pro's shipped source references only `@api`
 *    Free symbols (checked when Pro's checkout sits beside this one).
 * 9. One layout for Free and Pro (docs/ARCHITECTURE.md § Where files go):
 *    canonical role folders and no synonyms (Mock, Locked, views, Helpers,
 *    Utils, scripts, styles...); PascalCase folders of PHP and fixed asset
 *    folders (assets, js, css, images); a class suffix (Interface, DTO,
 *    Controller, Middleware, Exception) decides its folder and the folder
 *    demands the suffix; every widget is <Name>/<Name>.php and keeps its own
 *    scripts and styles; every script and stylesheet sits in assets/js or
 *    assets/css; src/Config holds only the composition root; and each Pro
 *    extension folder names the Free area it extends, with a listed
 *    Pro<Area> entry point.
 *
 * There is no allowlist; an exception is a violation.
 *
 * Run: php tests/architecture/check.php
 */

$plugin_root = dirname(__DIR__, 2) . '/';
$shared_layers = ['Core', 'Http', 'Elementor', 'Widgets', 'Loop', 'Conditions', 'TemplateLibrary', 'Integrations', 'Support'];
// Inside these modules only the named folders may reach Elementor: the rest is
// a builder-neutral engine.
$builder_neutral_modules = ['FormBuilder' => ['Widgets', 'Elementor']];
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
    $may_use_elementor = in_array($layer, ['Elementor', 'Widgets'], true)
        || (null !== $module && (!isset($builder_neutral_modules[$module]) || in_array($segments[2] ?? '', $builder_neutral_modules[$module], true)));

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
            $violations[] = sprintf('[Elementor API outside src/Elementor, src/Widgets and the Elementor-facing module folders] %s:%d: %s', $relative_path, $number + 1, $trimmed);
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

// Retired names, with what replaced them. Case-sensitive: `elemacyPopups` is
// the current API global, `ElemacyPopups` the retired one.
$retired_names = [
    '/elemacy-close-popup/' => 'elemacy-close-popup (use the class elemacy-popup-close)',
    '/data-elemacy-popup-(?:open|close|convert)\b/' => 'data-elemacy-popup-open/-close/-convert (use #elemacy-popup-{id}, elemacy-popup-close, elemacy-popup-convert)',
    '/elemacy-open-popup/' => 'elemacy-open-popup (use a link to #elemacy-popup-{id})',
    '/\bElemacyPopups\b/' => 'ElemacyPopups (the API is window.elemacyPopups)',
    '/wp_localize_script\([^,]+,\s*\'elemacyPopups\'/' => "the config global 'elemacyPopups' (the config is elemacyPopupsConfig)",
    '/elemacy-popup:shown/' => 'elemacy-popup:shown (the event is elemacy-popup:opened)',
    '/\belemacyPopupsAnalytics\b/' => 'elemacyPopupsAnalytics (the config is elemacyPopupsAnalyticsConfig)',
    '/\belemacyVisitCounts\b/' => 'elemacyVisitCounts (the global is elemacyPopupsVisitCounts)',
    '/\bElemacyElementorLifecycle\b/' => 'ElemacyElementorLifecycle (the global is elemacyElementorLifecycle)',
    '/\belemacy_widgets_form_submit\b/' => 'elemacy_widgets_form_submit (the form endpoint is elemacy_form_submit)',
    '/\belemacy_form_context\b/' => 'elemacy_form_context (forms carry no placement token)',
    '/\belemacy_widgets_loop_grid_pagination\b/' => 'elemacy_widgets_loop_grid_pagination (the loop pagination action is elemacy_loop_builder_pagination)',
];
// REST namespaces, URLs and route literals that leave out the version.
$unversioned_rest = [
    '/set_namespace\(\s*[\'"]elemacy[\'"]\s*\)/',
    '/rest_url\(\s*[\'"]\/?elemacy\/(?!v\d+\/)/',
    '/rest_url\(\s*\)\s*\)?\s*\.\s*[\'"]\/?elemacy\/(?!v\d+\/)/',
    '/[\'"`]\/(?:wp-json\/)?elemacy\/(?!v\d+\/)[a-z{]/',
];
$public_name_roots = ['Free' => $plugin_root];
if (is_dir(dirname($plugin_root) . '/elemacy-pro/src')) {
    $public_name_roots['Pro'] = dirname($plugin_root) . '/elemacy-pro/';
}

foreach ($public_name_roots as $plugin => $root) {
    foreach (['src', 'assets', 'src-react/src'] as $tree) {
        if (!is_dir($root . $tree)) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . $tree, FilesystemIterator::SKIP_DOTS)) as $file_info) {
            if (!in_array($file_info->getExtension(), ['php', 'js', 'ts', 'tsx', 'css', 'html'], true)) {
                continue;
            }

            $relative_path = $plugin . ':' . ltrim(str_replace($root, '', $file_info->getPathname()), '/');

            foreach (file($file_info->getPathname()) as $number => $line) {
                foreach ($retired_names as $pattern => $name) {
                    if (preg_match($pattern, $line)) {
                        $violations[] = sprintf('[retired public name] %s:%d: %s', $relative_path, $number + 1, $name);
                    }
                }
                foreach ($unversioned_rest as $pattern) {
                    if (preg_match($pattern, $line)) {
                        $violations[] = sprintf('[unversioned REST route] %s:%d: %s', $relative_path, $number + 1, trim($line));
                    }
                }
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

// Every widget catalog: the platform's own and each module's Config/widgets.php.
$widget_catalogs = array_merge(
    [$plugin_root . 'src/Widgets/Config/widgets.php'],
    glob($plugin_root . 'src/Modules/*/Config/widgets.php') ?: []
);
$widget_names_seen = 0;
foreach ($widget_catalogs as $catalog_path) {
    $catalog = ltrim(str_replace($plugin_root, '', $catalog_path), '/');
    preg_match_all("/'name'\\s*=>\\s*'([^']+)'/", (string) file_get_contents($catalog_path), $widget_names);
    $widget_names_seen += count($widget_names[1]);
    foreach ($widget_names[1] as $widget_name) {
        if (!preg_match('/^elemacy(-[a-z0-9]+)+$/', $widget_name)) {
            $violations[] = sprintf("[widget type naming] '%s' in %s must be elemacy- plus lowercase words joined by hyphens", $widget_name, $catalog);
        }
    }
}
if (0 === $widget_names_seen) {
    $violations[] = '[widget type naming] no widget type could be read from src/Widgets/Config/widgets.php or any src/Modules/*/Config/widgets.php';
}

/**
 * Every class, interface and trait Free ships, keyed by FQN: its file, kind,
 * tier tag(s), imports, and its code with comments removed.
 *
 * @return array<string, array{path: string, kind: string, tiers: string[], uses: array<string, string>, namespace: string, code: string}>
 */
$declared_symbols = static function (string $src_root): array {
    $symbols = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src_root, FilesystemIterator::SKIP_DOTS)) as $file_info) {
        if ('php' !== $file_info->getExtension()) {
            continue;
        }

        $source = (string) file_get_contents($file_info->getPathname());
        $doc = '';
        $code = '';
        $declaration = null;

        foreach (token_get_all($source) as $token) {
            $id = is_array($token) ? $token[0] : null;
            $text = is_array($token) ? $token[1] : $token;

            if (T_DOC_COMMENT === $id) {
                $doc = $text;
                continue;
            }
            if (T_COMMENT === $id) {
                continue;
            }
            if (null === $declaration && in_array($id, [T_CLASS, T_INTERFACE, T_TRAIT], true) && !preg_match('/::\s*$/', $code)) {
                $declaration = ['kind' => strtolower($text), 'doc' => $doc];
            } elseif (null === $declaration && !in_array($id, [T_WHITESPACE, T_FINAL, T_ABSTRACT], true)) {
                $doc = '';
            }
            $code .= $text;
        }

        if (null === $declaration || !preg_match('/^namespace\s+([^;]+);/m', $code, $namespace)
            || !preg_match('/^(?:final\s+|abstract\s+)?(?:class|interface|trait)\s+(\w+)/m', $code, $name)) {
            continue;
        }

        $uses = [];
        preg_match_all('/^use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $imports, PREG_SET_ORDER);
        foreach ($imports as $import) {
            $parts = explode('\\', $import[1]);
            $uses[$import[2] ?? end($parts)] = $import[1];
        }

        preg_match_all('/^\s*\*\s*@(api|internal)\b/m', $declaration['doc'], $tags);
        $symbols[$namespace[1] . '\\' . $name[1]] = [
            'path' => $file_info->getPathname(),
            'kind' => $declaration['kind'],
            'tiers' => $tags[1],
            'uses' => $uses,
            'namespace' => $namespace[1],
            'code' => $code,
        ];
    }

    return $symbols;
};

// 8. PHP extension contract (docs/CONTRACTS.md § PHP extension contract).
$free_symbols = $declared_symbols($plugin_root . 'src');
$is_api = static function (string $fqn) use ($free_symbols): bool {
    return ($free_symbols[$fqn]['tiers'] ?? []) === ['api'];
};

foreach ($free_symbols as $fqn => $symbol) {
    $relative_path = ltrim(str_replace($plugin_root, '', $symbol['path']), '/');

    // 8a. Exactly one stability tier.
    if (1 !== count($symbol['tiers'])) {
        $violations[] = sprintf('[stability tier] %s (%s) must carry exactly one of @api or @internal in its %s docblock', $fqn, $relative_path, $symbol['kind']);
    }

    if ('interface' !== $symbol['kind']) {
        continue;
    }

    // 8b. Interfaces live in a Contracts/ folder and end in Interface.
    if (false === strpos($relative_path, '/Contracts/') || !preg_match('/Interface$/', $fqn)) {
        $violations[] = sprintf('[interface convention] %s must live in a Contracts/ folder and its name must end in Interface', $relative_path);
    }

    // 8d. A stable interface's signatures name only stable Free types.
    if (!$is_api($fqn)) {
        continue;
    }
    preg_match_all('/function\s+\w+\s*\(([^)]*)\)\s*(?::\s*\??([\w\\\\]+))?/', $symbol['code'], $signatures, PREG_SET_ORDER);
    foreach ($signatures as $signature) {
        $types = isset($signature[2]) ? [$signature[2]] : [];
        preg_match_all('/(?:^|,)\s*\??([\w\\\\]+)\s+&?(?:\.\.\.)?\$/', $signature[1], $parameter_types);
        foreach (array_merge($types, $parameter_types[1]) as $type) {
            $head = explode('\\', ltrim($type, '\\'))[0];
            $resolved = '\\' === $type[0]
                ? ltrim($type, '\\')
                : (isset($symbol['uses'][$head]) ? $symbol['uses'][$head] . substr($type, strlen($head)) : $symbol['namespace'] . '\\' . $type);
            if (isset($free_symbols[$resolved]) && !$is_api($resolved)) {
                $violations[] = sprintf('[contract signature] @api %s names @internal %s', $fqn, $resolved);
            }
        }
    }
}

// 8c. Pro references only stable Free symbols.
if (is_dir(dirname($plugin_root) . '/elemacy-pro/src')) {
    $pro_root = dirname($plugin_root) . '/elemacy-pro/';

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pro_root . 'src', FilesystemIterator::SKIP_DOTS)) as $file_info) {
        if ('php' !== $file_info->getExtension()) {
            continue;
        }

        $code = '';
        foreach (token_get_all((string) file_get_contents($file_info->getPathname())) as $token) {
            if (!is_array($token) || !in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $code .= is_array($token) ? $token[1] : $token;
            }
        }

        preg_match_all('/(?:^use\s+|\\\\)(Elemacy\\\\[\w\\\\]+)/m', $code, $references);
        foreach (array_unique($references[1]) as $reference) {
            if (isset($free_symbols[$reference]) && !$is_api($reference)) {
                $violations[] = sprintf('[Pro uses internal] elemacy-pro/%s references @internal %s', ltrim(str_replace($pro_root, '', $file_info->getPathname()), '/'), $reference);
            } elseif (!isset($free_symbols[$reference])) {
                $violations[] = sprintf('[Pro uses unknown] elemacy-pro/%s references %s, which Free does not declare', ltrim(str_replace($pro_root, '', $file_info->getPathname()), '/'), $reference);
            }
        }
    }
}

// 9. One layout for both plugins (docs/ARCHITECTURE.md § Where files go).
$layout_roots = ['Free' => $plugin_root];
if (is_dir(dirname($plugin_root) . '/elemacy-pro/src')) {
    $layout_roots['Pro'] = dirname($plugin_root) . '/elemacy-pro/';
}
$role_synonyms = [
    'Mock' => 'Placeholders', 'Locked' => 'Placeholders', 'views' => 'Views', 'Helpers' => 'Support',
    'Utils' => 'Support', 'Utilities' => 'Support', 'Interfaces' => 'Contracts', 'Dtos' => 'DTO',
    'scripts' => 'js', 'styles' => 'css',
];
$suffix_folders = ['Interface' => 'Contracts', 'DTO' => 'DTO', 'Controller' => 'Controllers', 'Middleware' => 'Middlewares', 'Exception' => 'Exceptions'];
$asset_folders = ['assets', 'js', 'css', 'images'];
$composition_root = ['Free' => ['modules.php', 'api.php', 'index.php'], 'Pro' => ['extensions.php', 'index.php']];
$namespace_roots = ['Free' => 'Elemacy\\', 'Pro' => 'ElemacyPro\\'];
// Paths (plugin-relative) excused from rule 9. Empty for 1.0: an entry is a decision to record here.
$layout_exceptions = [];
$widget_bases = '(?:BaseWidget|LoopWidget|Widget_Base|Widget_[A-Z][A-Za-z_]*)';

foreach ($layout_roots as $plugin => $root) {
    $label = 'Free' === $plugin ? '' : 'elemacy-pro/';
    $asset_files = [];
    $widget_dirs = [];
    $tree = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . 'src', FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

    foreach ($tree as $info) {
        $relative = ltrim(str_replace($root, '', $info->getPathname()), '/');
        if (in_array($relative, $layout_exceptions, true)) {
            continue;
        }

        $parts = explode('/', $relative);
        $name = $info->getFilename();

        // 9a/9b. Folder names: canonical role folders, PascalCase PHP folders, fixed asset folders.
        if ($info->isDir()) {
            $in_assets = in_array('assets', $parts, true);

            if (isset($role_synonyms[$name]) || ('Http' === $name && count($parts) > 2)) {
                $violations[] = sprintf('[layout: role folder] %s%s/ is a synonym; use %s/', $label, $relative, $role_synonyms[$name] ?? 'Controllers');
            } elseif ($in_assets ? !in_array($name, $asset_folders, true) : !preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
                $violations[] = sprintf('[layout: folder case] %s%s/: folders of PHP are PascalCase; asset folders are assets, js, css or images', $label, $relative);
            }
            continue;
        }

        $extension = $info->getExtension();

        // 9d. Scripts and stylesheets live in an assets/js or assets/css folder.
        if (in_array($extension, ['js', 'css'], true)) {
            $asset_files[] = $relative;
            if (!preg_match('#(^|/)assets/(js|css)/[^/]+$#', $relative)) {
                $violations[] = sprintf('[layout: asset folder] %s%s is outside an assets/js or assets/css folder', $label, $relative);
            }
            continue;
        }

        if ('php' !== $extension) {
            continue;
        }

        // 9c. A class suffix decides its folder, and a role folder's classes carry its suffix.
        $stem = $info->getBasename('.php');
        $folder = basename(dirname($info->getPathname()));
        foreach ($suffix_folders as $suffix => $role_folder) {
            $has_suffix = substr($stem, -strlen($suffix)) === $suffix;
            if ($has_suffix && $folder !== $role_folder) {
                $violations[] = sprintf('[layout: suffix] %s%s ends in %s and belongs in a %s/ folder', $label, $relative, $suffix, $role_folder);
            } elseif (!$has_suffix && $folder === $role_folder && 'index' !== $stem) {
                $violations[] = sprintf('[layout: suffix] %s%s is in %s/ and its name must end in %s', $label, $relative, $role_folder, $suffix);
            }
        }

        // 9d. A concrete widget class is <Name>/<Name>.php.
        $code = (string) file_get_contents($info->getPathname());
        if (preg_match('/^(?!abstract)(?:final\s+)?class\s+(\w+)\s+extends\s+\\\\?(?:Elementor\\\\)?' . $widget_bases . '\b/m', $code, $match)) {
            if (basename(dirname($info->getPathname())) !== $match[1]) {
                $violations[] = sprintf('[layout: widget folder] %s%s: widget %s lives at %s/%s.php', $label, $relative, $match[1], $match[1], $match[1]);
            } else {
                $widget_dirs[] = dirname($relative);
            }
        }
    }

    // 9d. Every class a widget catalog lists is a widget folder too.
    foreach (array_merge(glob($root . 'src/*/Config/widgets.php') ?: [], glob($root . 'src/Modules/*/Config/widgets.php') ?: []) as $catalog) {
        preg_match_all('/^use\s+([\w\\\\]+);/m', (string) file_get_contents($catalog), $listed);
        foreach ($listed[1] as $class) {
            $path = 'src/' . str_replace('\\', '/', substr($class, strlen($namespace_roots[$plugin]))) . '.php';
            if (basename(dirname($path)) !== basename($path, '.php')) {
                $violations[] = sprintf('[layout: widget folder] %s%s lists %s, which is not at <Name>/<Name>.php', $label, ltrim(str_replace($root, '', $catalog), '/'), $class);
            }
        }
    }

    // 9d. A widget's own script or stylesheet (named after the widget) stays in its folder,
    // or in the assets/ of the module that owns it.
    foreach ($widget_dirs as $widget_dir) {
        $handle = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', basename($widget_dir)));
        $module_assets = preg_match('#^(src/Modules/[^/]+)/#', $widget_dir, $module) ? $module[1] . '/assets/' : null;
        foreach ($asset_files as $asset) {
            if (pathinfo($asset, PATHINFO_FILENAME) === $handle && 0 !== strpos($asset, $widget_dir . '/') && (null === $module_assets || 0 !== strpos($asset, $module_assets))) {
                $violations[] = sprintf('[layout: widget assets] %s%s belongs in %s/assets/', $label, $asset, $widget_dir);
            }
        }
    }

    // Shared assets: the root assets/<surface>/{js,css,images}/.
    if (is_dir($root . 'assets')) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . 'assets', FilesystemIterator::SKIP_DOTS)) as $info) {
            $relative = ltrim(str_replace($root, '', $info->getPathname()), '/');
            if (in_array($info->getExtension(), ['js', 'css'], true) && !preg_match('#^assets/[a-z-]+/(js|css)/[^/]+$#', $relative)) {
                $violations[] = sprintf('[layout: asset folder] %s%s is outside an assets/<surface>/js or /css folder', $label, $relative);
            }
        }
    }

    // 9e. The plugin-wide Config/ holds only the composition root.
    foreach (glob($root . 'src/Config/*') ?: [] as $config) {
        if (!in_array(basename($config), $composition_root[$plugin], true)) {
            $violations[] = sprintf('[layout: composition root] %ssrc/Config/%s belongs in its owner\'s Config/ folder', $label, basename($config));
        }
    }
}

// 9f. Each Pro extension folder names the Free area it extends and has one listed entry point.
if (isset($layout_roots['Pro'])) {
    $pro_root = $layout_roots['Pro'];
    $free_areas = array_map('basename', array_merge(glob($plugin_root . 'src/*', GLOB_ONLYDIR) ?: [], glob($plugin_root . 'src/Modules/*', GLOB_ONLYDIR) ?: []));
    $extension_list = (string) @file_get_contents($pro_root . 'src/Config/extensions.php');

    foreach (glob($pro_root . 'src/Extensions/*', GLOB_ONLYDIR) ?: [] as $extension_dir) {
        $area = basename($extension_dir);
        $entry = $extension_dir . '/Pro' . $area . '.php';

        if (!in_array($area, $free_areas, true)) {
            $violations[] = sprintf('[layout: Pro extension] elemacy-pro/src/Extensions/%s/ names no Free shared layer or module', $area);
        } elseif (!is_file($entry) || !preg_match('/class\s+Pro' . $area . '\s+implements\s+ExtensionInterface\b/', (string) file_get_contents($entry))) {
            $violations[] = sprintf('[layout: Pro extension] elemacy-pro/src/Extensions/%s/ needs Pro%s.php implementing ExtensionInterface', $area, $area);
        } elseif (!preg_match('/\bPro' . $area . '::class\b/', $extension_list)) {
            $violations[] = sprintf('[layout: Pro extension] Pro%s is not listed in elemacy-pro/src/Config/extensions.php', $area);
        }
    }
}

if ($violations) {
    fwrite(STDERR, "Architecture check failed:\n\n");

    foreach ($violations as $violation) {
        fwrite(STDERR, '  - ' . $violation . "\n");
    }

    fwrite(STDERR, "\nMove shared code into a shared layer, reach another module through a contract or hook, go through src/Elementor for Elementor APIs, keep planning references in docs/, migrate stored values you rename, use the final public popup names, version every REST route, tag every Free symbol @api or @internal with Pro using only @api ones, and put each file where docs/ARCHITECTURE.md § Where files go says. There is no allowlist.\n");

    exit(1);
}

echo "Architecture check: layering, Elementor boundary, planning-reference, persisted-identifier, retired-public-name, versioned-REST, dynamic-tag-name, widget-type-naming, PHP 7.4 syntax-floor, PHP-extension-contract and layout rules all pass.\n";
