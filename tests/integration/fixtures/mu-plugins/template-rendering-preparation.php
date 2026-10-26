<?php

/**
 * Fixtures for task 3.2's TemplateRenderer::prepare()/get_prepared() and
 * TemplateAssetsRegistrar::prepare() tests.
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

add_action('elementor/widgets/register', static function ($widgets_manager): void {
    if (!class_exists('Elemacy_Fixture_Throwing_Widget')) {
        class Elemacy_Fixture_Throwing_Widget extends \Elementor\Widget_Base
        {
            public function get_name()
            {
                return 'elemacy-fixture-throws';
            }

            public function get_title()
            {
                return 'Fixture Throwing Widget';
            }

            public function get_icon()
            {
                return 'eicon-code';
            }

            public function get_categories()
            {
                return ['general'];
            }

            protected function register_controls()
            {
            }

            // Elementor's Document::save() generates a plain-text excerpt by
            // calling every widget's render_plain_content() (base
            // implementation: render_content() -> render()) synchronously at
            // save time, before this fixture is ever used to test frontend
            // preparation. Overriding it to a no-op keeps document creation
            // itself from throwing, exactly as a real widget would if its
            // render() depended on frontend-only context.
            public function render_plain_content()
            {
            }

            protected function render()
            {
                throw new \RuntimeException('elemacy-fixture-throws widget always throws');
            }
        }
    }

    $widgets_manager->register(new Elemacy_Fixture_Throwing_Widget());

    // A variant of the same widget that raises a PHP Error (not an Exception).
    if (!class_exists('Elemacy_Fixture_Type_Error_Widget')) {
        class Elemacy_Fixture_Type_Error_Widget extends Elemacy_Fixture_Throwing_Widget
        {
            public function get_name()
            {
                return 'elemacy-fixture-type-error';
            }

            protected function render()
            {
                // Deliberate TypeError: an object passed for a string parameter.
                (static function (string $value): string {
                    return $value;
                })(...[new \stdClass()]);
            }
        }
    }

    $widgets_manager->register(new Elemacy_Fixture_Type_Error_Widget());
});

/**
 * Marks a document (via `_elemacy_fixture_item_marker` postmeta, any
 * non-empty value) so its rendered fragment carries a sibling <style> tag
 * before the `.elementor` root plus the *ambient* current post ID at render
 * time — not the document's own ID — so a test can vary "the current loop
 * item" across two prepare() calls for the same document/context and assert
 * each placement's cached fragment reflects its own item.
 *
 * `elementor/frontend/the_content` fires before Frontend::get_builder_content()
 * restores the previous document (Documents_Manager::restore_document()), so
 * documents->get_current() here is still the document being rendered.
 */
add_filter('elementor/frontend/the_content', static function (string $content): string {
    if (!class_exists('\Elementor\Plugin')) {
        return $content;
    }

    $current = \Elementor\Plugin::instance()->documents->get_current();
    if (!$current || '' === (string) get_post_meta($current->get_main_id(), '_elemacy_fixture_item_marker', true)) {
        return $content;
    }

    $sibling = '<style id="elemacy-fixture-sibling-style">.elemacy-fixture-sibling{color:red}</style>';
    $marker = '<span data-elemacy-fixture-current-post>' . (int) get_the_ID() . '</span>';

    return $sibling . $marker . $content;
});

/**
 * A document marked with `_elemacy_fixture_render_nothing` renders to an empty
 * string, as when Elementor has no content to return for it.
 */
add_filter('elementor/frontend/the_content', static function (string $content): string {
    if (!class_exists('\\Elementor\\Plugin')) {
        return $content;
    }

    $current = \Elementor\Plugin::instance()->documents->get_current();

    return $current && '' !== (string) get_post_meta($current->get_main_id(), '_elemacy_fixture_render_nothing', true) ? '' : $content;
}, 20);
