<?php

/** A test-only host using the same collection/rendering APIs as builder modules. */
defined('ELEMACY_INTEGRATION_TOKEN') || exit;

add_filter('elementor/frontend/print_google_fonts', '__return_false');

function elemacy_fixture_foreign_document(): int
{
    $host = get_queried_object_id();
    if (get_post_meta($host, '_elemacy_fixture_environment', true) !== ELEMACY_INTEGRATION_TOKEN) {
        return 0;
    }
    return (int) get_post_meta($host, '_elemacy_fixture_document', true);
}

add_action('elemacy/action/template_assets/collect', static function ($registrar): void {
    $document = elemacy_fixture_foreign_document();
    if ($document) {
        $registrar->register($document);
    }
});

add_shortcode('elemacy_fixture_document', static function (): string {
    $document = elemacy_fixture_foreign_document();
    return $document ? (new Elemacy\Elementor\Rendering\TemplateRenderer())->render($document) : '';
});
