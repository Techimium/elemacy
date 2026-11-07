<?php

/**
 * Theme Builder targeting and request-display checks. Required from run.php,
 * so it shares that file's stubs, fixtures ($prec_entry, FixedResolverCache,
 * the prec/* conditions) and check()/conclude().
 */

use Elemacy\Conditions\DTO\ConditionRuleDTO;
use Elemacy\Modules\ThemeBuilder\ThemeBuilder;
use Elemacy\TemplateLibrary\TemplateResolver;
use Elemacy\TemplateLibrary\TypeDefinition;
use Elemacy\TemplateLibrary\TypeRegistry;

/**
 * Runs $test against a fresh TypeRegistry singleton, restoring the shared one
 * afterwards so other checks keep the types they registered.
 */
function elemacy_with_fresh_type_registry(callable $test)
{
    $instance = new ReflectionProperty(TypeRegistry::class, 'instance');
    $instance->setAccessible(true);
    $shared = $instance->getValue();
    $instance->setValue(null, new TypeRegistry());

    try {
        return $test(TypeRegistry::instance());
    } finally {
        $instance->setValue(null, $shared);
    }
}

check('ThemeBuilder registers 404 as location-wide and every other type with its broadest condition as the default', static function () {
    return elemacy_with_fresh_type_registry(static function (TypeRegistry $registry): bool {
        $register = new ReflectionMethod(ThemeBuilder::class, 'register_types');
        $register->setAccessible(true);
        $register->invoke(new ThemeBuilder());
        do_action('init');

        $expected = [
            'header'  => 'general/entire_site',
            'footer'  => 'general/entire_site',
            'single'  => 'singular/post_type',
            'archive' => 'archive/post_type',
            'search'  => 'search/all',
        ];

        foreach ($expected as $name => $condition) {
            $type = $registry->get($name);
            if ($type === null || $type->targets_location()
                || $type->default_conditions !== [['type' => $condition, 'operator' => 'include', 'value' => '']]) {
                return false;
            }
        }

        $not_found = $registry->get('404');

        return $not_found !== null && $not_found->targets_location() && $not_found->default_conditions === [];
    });
});

check('a TypeDefinition targets conditions unless it says otherwise', static fn() =>
    !(new TypeDefinition('fixture', 'Fixture', 'theme', 'theme-builder'))->targets_location());

check('ConditionRuleDTO::any_include() is true only when some rule includes', static fn() =>
    ConditionRuleDTO::any_include(ConditionRuleDTO::collection([['type' => 'a', 'operator' => 'exclude'], ['type' => 'b']]))
    && !ConditionRuleDTO::any_include(ConditionRuleDTO::collection([['type' => 'a', 'operator' => 'exclude']]))
    && !ConditionRuleDTO::any_include([]));

check('a condition-targeted template with no rules is never displayed, even when nothing else matches', static function () use ($prec_resolve, $prec_entry) {
    return null === $prec_resolve([['id' => 1, 'conditions' => []]])
        && null === $prec_resolve([['id' => 1, 'conditions' => []], $prec_entry(2, [['prec/object_nomatch']])])
        && 2 === $prec_resolve([['id' => 1, 'conditions' => []], $prec_entry(2, [['prec/site']])]);
});

check('a condition-targeted template whose rules only exclude is never displayed', static fn() =>
    null === $prec_resolve([$prec_entry(1, [['prec/object_nomatch', 'exclude']])]));

check('a location-wide type resolves to its newest item and ignores rules it never offered', static function () use ($prec_entry) {
    return elemacy_with_fresh_type_registry(static function (TypeRegistry $registry) use ($prec_entry): bool {
        $registry->register(new TypeDefinition('fixture_404', '404', 'theme', 'theme-builder', null, TypeDefinition::TARGETS_LOCATION));

        $resolve = static function (array $entries): ?int {
            TemplateResolver::reset();

            return (new TemplateResolver(new FixedResolverCache(['fixture_404' => $entries])))->resolve('fixture_404');
        };

        // Index order is newest first.
        return 7 === $resolve([['id' => 7, 'conditions' => []], ['id' => 3, 'conditions' => []]])
            && 7 === $resolve([$prec_entry(7, [['prec/object_nomatch']]), ['id' => 3, 'conditions' => []]])
            && null === $resolve([]);
    });
});

/* ── One request-display decision ─────────────────────────────────── */

/**
 * Elementor Pro's public location helper, controlled per check through
 * $GLOBALS['__pro_served_locations']. Defined here, after every other check
 * file, so nothing earlier sees Elementor Pro as active.
 */
function elementor_location_exits($location, $check_match = false)
{
    return in_array($location, $GLOBALS['__pro_served_locations'] ?? [], true);
}

/**
 * A manager whose header, footer and Single location each resolve to a fixed
 * template, every one matching through the site-wide prec/site condition.
 */
function elemacy_display_manager(): \Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager
{
    TemplateResolver::reset();
    $entry = static fn(int $id): array => ['id' => $id, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => '']]];
    $locations = new \Elemacy\Modules\ThemeBuilder\Services\LocationRegistry();
    $locations->register('single', static fn(): bool => true);

    return new \Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager(
        \Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager::instance(),
        $locations,
        new TemplateResolver(new FixedResolverCache(['header' => [$entry(11)], 'footer' => [$entry(12)], 'single' => [$entry(13)]])),
        TypeRegistry::instance(),
        // Without Elementor loaded the real renderer renders nothing.
        new class extends \Elemacy\Elementor\Rendering\TemplateRenderer {
            public function render(int $id): string
            {
                return '<div class="elementor">template ' . $id . '</div>';
            }
        }
    );
}

check('on an ordinary front-end page the header, footer and location template are all displayed', static function () {
    $manager = elemacy_display_manager();

    return 11 === $manager->get_header_id() && 12 === $manager->get_footer_id() && 13 === $manager->get_location_template_id();
});

check('an embed is never themed: no header, footer or location template, and no theme template override', static function () {
    $GLOBALS['__wp_is_embed'] = true;

    try {
        $manager = elemacy_display_manager();

        return null === $manager->get_header_id()
            && null === $manager->get_footer_id()
            && null === $manager->get_location_template_id()
            && 'embed-template.php' === $manager->override_template('embed-template.php')
            && !$manager->replaces_native_chrome('header');
    } finally {
        unset($GLOBALS['__wp_is_embed']);
    }
});

check('a location Elementor Pro serves is left to it, and every other location is still served', static function () {
    $GLOBALS['__pro_served_locations'] = ['header', 'single'];
    do_action('wp');

    try {
        $manager = elemacy_display_manager();

        return null === $manager->get_header_id()
            && 12 === $manager->get_footer_id()
            && null === $manager->get_location_template_id()
            && !$manager->replaces_native_chrome('header')
            && $manager->replaces_native_chrome('footer');
    } finally {
        unset($GLOBALS['__pro_served_locations']);
    }
});

check('a header or footer is used as published: one that renders nothing still replaces the theme\'s own chrome, and deciding renders neither', static function () {
    TemplateResolver::reset();
    \Elemacy\Elementor\Rendering\TemplateRenderer::reset();
    $entry = static fn(int $id): array => ['id' => $id, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => '']]];
    $rendered = [];
    $manager = new \Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager(
        \Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager::instance(),
        new \Elemacy\Modules\ThemeBuilder\Services\LocationRegistry(),
        new TemplateResolver(new FixedResolverCache(['header' => [$entry(21)], 'footer' => [$entry(22)]])),
        TypeRegistry::instance(),
        new class ($rendered) extends \Elemacy\Elementor\Rendering\TemplateRenderer {
            private array $rendered;

            public function __construct(array &$rendered)
            {
                parent::__construct();
                $this->rendered = &$rendered;
            }

            public function render(int $id): string
            {
                $this->rendered[] = $id;

                return 21 === $id ? '' : '<div class="elementor">footer</div>';
            }
        }
    );

    return $manager->replaces_native_chrome('header') && $manager->replaces_native_chrome('footer') && [] === $rendered;
});

check('Elementor Pro is not asked before the main query has run', static function () {
    $GLOBALS['__pro_served_locations'] = ['header'];
    $fired = $GLOBALS['__wp_actions_fired']['wp'] ?? null;
    unset($GLOBALS['__wp_actions_fired']['wp']);

    try {
        return !(new \Elemacy\Modules\ThemeBuilder\Compatibility\ElementorProLocations())->serves('header');
    } finally {
        unset($GLOBALS['__pro_served_locations']);
        if (null !== $fired) {
            $GLOBALS['__wp_actions_fired']['wp'] = $fired;
        }
    }
});

check('Elementor Pro serves 404 pages from its single location and search results from its archive location', static function () {
    $GLOBALS['__pro_served_locations'] = ['single'];
    do_action('wp');

    try {
        $pro = new \Elemacy\Modules\ThemeBuilder\Compatibility\ElementorProLocations();

        return $pro->serves('404') && $pro->serves('single') && !$pro->serves('search') && !$pro->serves('header');
    } finally {
        unset($GLOBALS['__pro_served_locations']);
    }
});

check('the served location template gives the page its elementor-page body class, and nothing else does', static function () {
    $manager = elemacy_display_manager();
    $before = $manager->add_location_body_class(['home']);
    $manager->override_template('index.php');
    $after = $manager->add_location_body_class(['home']);

    return ['home'] === $before && in_array('elementor-page-13', $after, true);
});

/* ── Template display summary ──────────────────────────────────────── */

if (!function_exists('get_the_title')) {
    function get_the_title($post = 0)
    {
        return 'Template #' . (int) $post;
    }
}

/**
 * @param array<string, array<int, array{id:int, conditions:array}>> $index
 */
function elemacy_display_summary(array $index): \Elemacy\Modules\ThemeBuilder\Services\TemplateDisplaySummary
{
    $types = new TypeRegistry();
    $types->register(new TypeDefinition('header', 'Header', 'theme', 'theme-builder'));
    $types->register(new TypeDefinition('404', '404 Page', 'theme', 'theme-builder', null, TypeDefinition::TARGETS_LOCATION));

    return new \Elemacy\Modules\ThemeBuilder\Services\TemplateDisplaySummary(new FixedResolverCache($index), $types);
}

$summary_rules = static fn(array $pairs): array => ConditionRuleDTO::collection(array_map(
    static fn(array $pair): array => ['type' => $pair[0], 'operator' => $pair[1] ?? 'include', 'value' => $pair[2] ?? ''],
    $pairs
));

check('a published template with no inclusion rule is reported as not displayed', static function () use ($summary_rules) {
    $display = elemacy_display_summary([])->describe(10, 'header', 'publish', $summary_rules([['prec/site', 'exclude']]));

    return 'not_displayed' === $display['state'] && [] === $display['summary'] && 1 === $display['exclusions'];
});

check('a template naming an unavailable condition is reported as suspended, naming it', static function () use ($summary_rules) {
    $display = elemacy_display_summary([])->describe(10, 'header', 'publish', $summary_rules([['prec/site'], ['fixture/missing', 'exclude']]));

    return 'suspended' === $display['state'] && ['fixture/missing'] === $display['unavailable'] && null === $display['overridden_by'];
});

check('a displayed template summarizes its inclusions and is not overridden when no newer template shares one', static function () use ($summary_rules) {
    $index = ['header' => [
        ['id' => 20, 'conditions' => [['type' => 'prec/type', 'operator' => 'include', 'value' => '']]],
        ['id' => 10, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => '']]],
    ]];
    $display = elemacy_display_summary($index)->describe(10, 'header', 'publish', $summary_rules([['prec/site']]));

    return 'displayed' === $display['state'] && ['prec/site'] === $display['summary'] && null === $display['overridden_by'];
});

check('a newer published template with an identical inclusion rule is reported as overriding the older one', static function () use ($summary_rules) {
    $index = ['header' => [
        ['id' => 30, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => '']]],
        ['id' => 10, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => '']]],
    ]];
    $summary = elemacy_display_summary($index);
    $older = $summary->describe(10, 'header', 'publish', $summary_rules([['prec/site']]));
    $newer = $summary->describe(30, 'header', 'publish', $summary_rules([['prec/site']]));

    return ['id' => 30, 'title' => 'Template #30'] === $older['overridden_by'] && null === $newer['overridden_by'];
});

check('a suspended newer template overrides nothing', static function () use ($summary_rules) {
    $index = ['header' => [
        ['id' => 30, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => ''], ['type' => 'fixture/missing', 'operator' => 'exclude', 'value' => '']]],
        ['id' => 10, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => '']]],
    ]];

    return null === elemacy_display_summary($index)->describe(10, 'header', 'publish', $summary_rules([['prec/site']]))['overridden_by'];
});

check('a location-wide template is displayed on its whole location and overridden by any newer one', static function () {
    $index = ['404' => [['id' => 31, 'conditions' => []], ['id' => 11, 'conditions' => []]]];
    $summary = elemacy_display_summary($index);
    $older = $summary->describe(11, '404', 'publish', []);

    return 'displayed' === $older['state'] && ['Every 404 Page'] === $older['summary']
        && 31 === $older['overridden_by']['id'] && null === $summary->describe(31, '404', 'publish', [])['overridden_by'];
});

check('an unpublished template is reported as unpublished, never as overridden', static function () use ($summary_rules) {
    $index = ['header' => [['id' => 30, 'conditions' => [['type' => 'prec/site', 'operator' => 'include', 'value' => '']]]]];
    $display = elemacy_display_summary($index)->describe(10, 'header', 'draft', $summary_rules([['prec/site']]));

    return 'unpublished' === $display['state'] && null === $display['overridden_by'] && ['prec/site'] === $display['summary'];
});

check('the retired template-types filter is gone: types are added through TypeRegistry, which saving validates against', static function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ELEMACY_PATH . 'src', FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ('php' === $file->getExtension() && false !== strpos((string) file_get_contents($file->getPathname()), 'theme_builder/template_types')) {
            return false;
        }
    }

    return !defined(\Elemacy\Core\Hooks::class . '::THEME_BUILDER_TEMPLATE_TYPES_FILTER');
});

check('the template types endpoint describes how each type targets requests and what a new one starts with', static function () {
    return elemacy_with_fresh_type_registry(static function (TypeRegistry $registry): bool {
        $registry->register(new TypeDefinition('header', 'Header', 'theme', 'theme-builder', null, TypeDefinition::TARGETS_CONDITIONS, [['type' => 'general/entire_site', 'operator' => 'include', 'value' => '']]));
        $registry->register(new TypeDefinition('404', '404 Page', 'theme', 'theme-builder', null, TypeDefinition::TARGETS_LOCATION));
        $registry->register(new TypeDefinition('popup', 'Popup', 'popup', 'popups'));

        $types = (new \Elemacy\Modules\ThemeBuilder\Services\ThemeBuilderManager(
            \Elemacy\Modules\ThemeBuilder\Compatibility\CompatibilityManager::instance(),
            new \Elemacy\Modules\ThemeBuilder\Services\LocationRegistry(),
            TemplateResolver::instance(),
            $registry,
            new \Elemacy\Elementor\Rendering\TemplateRenderer()
        ))->get_available_template_types();

        return [
            ['value' => 'header', 'label' => 'Header', 'targeting' => 'conditions', 'default_conditions' => [['type' => 'general/entire_site', 'operator' => 'include', 'value' => '']]],
            ['value' => '404', 'label' => '404 Page', 'targeting' => 'location', 'default_conditions' => []],
        ] === $types;
    });
});

/* ── Post Type Archive condition ───────────────────────────────────── */

/**
 * Whether "Post Type Archive: $value" matches an archive of $kind (a
 * taxonomy archive's term in $taxonomy), with genre registered for books and
 * category for posts.
 */
function elemacy_post_type_archive_matches(string $value, array $archive, ?string $taxonomy = null): bool
{
    $GLOBALS['__wp_archive'] = $archive;
    $GLOBALS['__wp_taxonomies'] = ['category' => ['post'], 'genre' => ['book'], 'topic' => ['post', 'book']];
    $previous = $GLOBALS['__queried_object'];

    if (null !== $taxonomy) {
        $term = new WP_Term(7, $taxonomy, ['name' => 'Fixture']);
        $GLOBALS['__queried_object'] = $term;
    }

    try {
        return (new \Elemacy\Conditions\Archive\PostType())->check(ConditionRuleDTO::from_array(['type' => 'archive/post_type', 'value' => $value]));
    } finally {
        unset($GLOBALS['__wp_archive'], $GLOBALS['__wp_taxonomies']);
        $GLOBALS['__queried_object'] = $previous;
    }
}

check('"Post Type Archive: Posts" matches a category archive from the request, so an empty category matches too', static fn() =>
    elemacy_post_type_archive_matches('post', ['kind' => 'category'], 'category'));

check('"Post Type Archive: Posts" matches date and author archives; a custom post type does not', static fn() =>
    elemacy_post_type_archive_matches('post', ['kind' => 'date'])
    && elemacy_post_type_archive_matches('post', ['kind' => 'author'])
    && !elemacy_post_type_archive_matches('book', ['kind' => 'author'])
    && !elemacy_post_type_archive_matches('book', ['kind' => 'date']));

check('a taxonomy archive belongs to the post types the taxonomy is registered for', static fn() =>
    elemacy_post_type_archive_matches('book', ['kind' => 'tax'], 'genre')
    && !elemacy_post_type_archive_matches('post', ['kind' => 'tax'], 'genre')
    && elemacy_post_type_archive_matches('post', ['kind' => 'tax'], 'topic')
    && elemacy_post_type_archive_matches('book', ['kind' => 'tax'], 'topic'));

check('a post type archive matches only its own post type', static fn() =>
    elemacy_post_type_archive_matches('book', ['kind' => 'post_type', 'post_type' => 'book'])
    && !elemacy_post_type_archive_matches('post', ['kind' => 'post_type', 'post_type' => 'book']));

check('Canvas is read from the template served, so body_class filters run once per page and cannot defeat the check', static fn() =>
    false === strpos(elemacy_code_without_comments(ELEMACY_PATH . 'src/Modules/ThemeBuilder/Services/ThemeBuilderManager.php'), 'get_body_class('));
