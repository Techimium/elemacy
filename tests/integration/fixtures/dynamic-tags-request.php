<?php

/**
 * Builds Elementor's dynamic tag list in its own PHP process and prints
 * {groups, tags, fired} as JSON. See DynamicTagsRegistrationTest.
 *
 * Elementor builds its tag list once per process, and a WooCommerce class
 * cannot be undefined once declared, so each scenario needs a fresh process.
 *
 * Input on STDIN: {woocommerce: bool, modules: string[]|null, extension: bool}.
 * `modules` replaces the stored active modules through a pre_option filter
 * seeded before WordPress loads; `extension` hooks
 * Hooks::DYNAMIC_TAGS_REGISTER_ACTION the way an add-on would, adding one
 * new tag and one under a built-in tag's name.
 */

$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$fired = 0;

$GLOBALS['wp_filter'] = [
    'elemacy/action/dynamic_tags/register' => [10 => [[
        'function' => static function ($manager) use (&$fired, $input): void {
            $fired++;

            if (!$input['extension']) {
                return;
            }

            if (!class_exists('Elemacy_Fixture_Extension_Tag')) {
                class Elemacy_Fixture_Extension_Tag extends \Elementor\Core\DynamicTags\Tag
                {
                    public function get_name()
                    {
                        return 'elemacy-fixture-extension-tag';
                    }

                    public function get_title()
                    {
                        return 'Fixture Extension Tag';
                    }

                    public function get_group()
                    {
                        return 'elemacy';
                    }

                    public function get_categories()
                    {
                        return [\Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY];
                    }

                    public function render()
                    {
                    }
                }

                class Elemacy_Fixture_Post_Title_Override extends Elemacy_Fixture_Extension_Tag
                {
                    public function get_name()
                    {
                        return 'elemacy-post-title';
                    }
                }
            }

            $manager->register(new Elemacy_Fixture_Extension_Tag());
            $manager->register(new Elemacy_Fixture_Post_Title_Override());
        },
        'accepted_args' => 1,
    ]]],
];

if (null !== $input['modules']) {
    $GLOBALS['wp_filter']['pre_option_elemacy_active_modules'] = [10 => [[
        'function' => static fn() => $input['modules'],
        'accepted_args' => 1,
    ]]];
}

require dirname(__DIR__) . '/bootstrap.php';

if ($input['woocommerce'] && !class_exists('WooCommerce')) {
    // Only the class's presence is checked when tags register.
    class WooCommerce
    {
    }
}

$manager = \Elementor\Plugin::$instance->dynamic_tags;
$manager->get_tags();
// A second build in the same request must not register anything again.
$tags = $manager->get_tags();

$groups = (new ReflectionProperty($manager, 'tags_groups'));
$groups->setAccessible(true);

// The plugin's own tags and this file's extension tags; not other fixtures'.
$elemacy_tags = [];
foreach ($tags as $name => $info) {
    if (0 === strpos($info['class'], 'Elemacy\\')
        || in_array($info['class'], ['Elemacy_Fixture_Extension_Tag', 'Elemacy_Fixture_Post_Title_Override'], true)) {
        $elemacy_tags[$name] = $info['class'];
    }
}

echo wp_json_encode([
    'groups' => array_map(static fn(array $group) => $group['title'], array_filter(
        $groups->getValue($manager),
        static fn(string $name) => 0 === strpos($name, 'elemacy'),
        ARRAY_FILTER_USE_KEY
    )),
    'tags' => $elemacy_tags,
    'fired' => $fired,
]);
