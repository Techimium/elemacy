<?php

/**
 * A minimal Elementor widget registered exactly as a real third-party addon
 * would — directly on `elementor/widgets/register`, entirely outside Elemacy's
 * own WidgetManager/WidgetService — with its own script dependency, so tests
 * can prove Elemacy's popup asset preparation delivers ANY matched document's
 * widget dependencies, not just its own.
 */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

add_action('elementor/widgets/register', static function ($widgets_manager): void {
    if (!class_exists('Elemacy_Fixture_Third_Party_Widget')) {
        class Elemacy_Fixture_Third_Party_Widget extends \Elementor\Widget_Base
        {
            public function get_name()
            {
                return 'elemacy-fixture-thirdparty';
            }

            public function get_title()
            {
                return 'Fixture Third-Party Widget';
            }

            public function get_icon()
            {
                return 'eicon-code';
            }

            public function get_categories()
            {
                return ['general'];
            }

            public function get_script_depends()
            {
                return ['elemacy-fixture-thirdparty'];
            }

            public function get_style_depends()
            {
                return ['elemacy-fixture-thirdparty'];
            }

            protected function register_controls()
            {
            }

            protected function render()
            {
                echo '<div class="elemacy-fixture-thirdparty" data-elemacy-fixture-thirdparty>Third-party fixture widget</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static fixture markup.
            }
        }
    }

    $widgets_manager->register(new Elemacy_Fixture_Third_Party_Widget());
});

add_action('wp_enqueue_scripts', static function (): void {
    wp_register_script('elemacy-fixture-thirdparty', plugins_url('popup-third-party-widget.js', __FILE__), ['elementor-frontend'], ELEMACY_INTEGRATION_TOKEN, true);
    wp_register_style('elemacy-fixture-thirdparty', plugins_url('popup-third-party-widget.css', __FILE__), [], ELEMACY_INTEGRATION_TOKEN);
});
