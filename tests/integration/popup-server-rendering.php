<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Conditions\ConditionRepository;
use Elemacy\Modules\Popups\Constants\MetaKeys;
use Elemacy\TemplateLibrary\Constants\MetaKeys as LibraryMetaKeys;
use Elemacy\TemplateLibrary\TypeRegistry;
use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

/**
 * Which matched popups the server outputs, and where. Every page is rendered in
 * its own process (ThemePageRequest) so each theme's header.php loads as it does
 * on a real request.
 */
final class PopupServerRenderingTest extends IntegrationTestCase
{
    private const THEME = 'theme-generic-chrome';

    private int $host_id;
    private array $owned = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->host_id = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Popup server rendering host', 'post_content' => 'Plain content, no Elementor.',
        ], true);
        self::assertIsInt($this->host_id);

        update_option('elementor_element_cache_ttl', 'disable');
    }

    protected function tearDown(): void
    {
        try {
            foreach (array_reverse($this->owned) as $id) {
                wp_delete_post($id, true);
            }
            wp_delete_post($this->host_id, true);
        } finally {
            delete_option('elementor_element_cache_ttl');
            parent::tearDown();
        }
    }

    /** A published popup of $type shown on the entire site. */
    private function create_popup(string $marker, string $type = 'popup', array $elements = [], array $rules = [], array $triggers = []): int
    {
        $id = $this->documents->create($marker, 'popup', 'publish', $elements ?: LibraryDocument::heading($marker));
        $this->owned[] = $id;

        update_post_meta($id, LibraryMetaKeys::TEMPLATE_TYPE, $type);
        (new ConditionRepository())->save($id, TypeRegistry::instance()->get($type)->default_conditions);

        if ($rules) {
            update_post_meta($id, MetaKeys::RULES, $rules);
        }
        if ($triggers) {
            update_post_meta($id, MetaKeys::TRIGGERS, $triggers);
        }

        return $id;
    }

    private function widget(string $type): array
    {
        return [[
            'id' => 'eac9501', 'elType' => 'widget', 'widgetType' => $type, 'settings' => [], 'elements' => [],
        ]];
    }

    private function render_host(string $theme = self::THEME, string $user = 'guest'): string
    {
        return ThemePageRequest::render($theme, get_permalink($this->host_id), $user)['html'];
    }

    /** The opening tag of popup $id's root, or '' when it is not in $html. */
    private function root(string $html, int $id): string
    {
        return 1 === preg_match('#<div class="elemacy-popup elemacy-popup-' . $id . ' [^>]*>#', $html, $match) ? $match[0] : '';
    }

    private function in_config(string $html, int $id): bool
    {
        return 1 === preg_match('#"id":' . $id . '[,}]#', $html);
    }

    public function testAPasswordProtectedPopupIsNeverDelivered(): void
    {
        $protected = $this->create_popup('Protected popup marker');
        wp_update_post(['ID' => $protected, 'post_password' => 'secret']);
        $open = $this->create_popup('Open popup marker');

        $html = $this->render_host();

        self::assertSame('', $this->root($html, $protected), 'A password-protected popup must not be in the page: ' . $html);
        self::assertFalse($this->in_config($html, $protected), 'Nor in the browser config.');
        self::assertStringNotContainsString('Protected popup marker', $html);
        self::assertNotSame('', $this->root($html, $open), 'A control popup on the same page is still delivered.');
        self::assertTrue($this->in_config($html, $open));
    }

    public function testViewingAPopupsOwnPageOutputsNoPopupRootAndNoConfig(): void
    {
        $viewed = $this->create_popup('Viewed popup marker');
        $other = $this->create_popup('Other site-wide popup marker');

        $html = ThemePageRequest::render(self::THEME, get_permalink($viewed), 'admin')['html'];

        self::assertStringContainsString('Viewed popup marker', $html, "The previewed popup's own content is the page: " . $html);
        self::assertStringNotContainsString('data-elemacy-popup-id=', $html, 'No popup root is printed over a popup\'s own page.');
        self::assertStringNotContainsString('elemacyPopups', $html, 'No popup config is printed over a popup\'s own page.');
        self::assertStringNotContainsString('Other site-wide popup marker', $html);
        self::assertNotSame($viewed, $other);
    }

    public function testPopupsThatRenderNothingOrThrowAreDroppedWhileAHealthyOneStays(): void
    {
        $empty = $this->create_popup('Empty popup');
        update_post_meta($empty, '_elemacy_fixture_render_nothing', '1');
        $throws = $this->create_popup('Throwing popup', 'popup', $this->widget('elemacy-fixture-throws'));
        $healthy = $this->create_popup('Healthy popup marker');

        $html = $this->render_host();

        foreach ([$empty, $throws] as $dropped) {
            self::assertSame('', $this->root($html, $dropped), "Popup $dropped renders nothing, so it has no root: " . $html);
            self::assertFalse($this->in_config($html, $dropped), "Popup $dropped has no entry in the browser config.");
        }
        self::assertNotSame('', $this->root($html, $healthy), 'The healthy popup on the same page is delivered.');
        self::assertTrue($this->in_config($html, $healthy));
        self::assertStringContainsString('Healthy popup marker', $html);
    }

    public function testADefaultTopBarIsPrintedVisibleWhileOneWithAFrequencyCapIsTemplated(): void
    {
        $default = $this->create_popup('Default bar marker', 'topbar');
        $capped = $this->create_popup('Capped bar marker', 'topbar', [], [['id' => 'r1', 'type' => 'frequency_cap', 'params' => ['max' => 3]]]);
        $bottom = $this->create_popup('Bottom bar marker', 'topbar');
        update_post_meta($bottom, '_elementor_page_settings', [\Elemacy\Modules\Popups\Support\DisplayKeys::POSITION => 'bottom']);

        $html = $this->render_host();

        $root = $this->root($html, $default);
        self::assertNotSame('', $root, 'The default bar is delivered: ' . $html);
        self::assertStringNotContainsString(' hidden', $root, 'A default top bar is printed without the hidden attribute.');
        self::assertStringContainsString('is-open', $root);
        self::assertStringContainsString('data-elemacy-prerendered', $root);
        self::assertStringContainsString('aria-hidden="false"', $root);
        self::assertSame(
            1,
            preg_match('#data-elemacy-popup-id="' . $default . '".*?<div [^>]*class="[^"]*elemacy-popup__box[^"]*"[^>]*>\s*<div class="elemacy-popup__scroll">.*?Default bar marker.*?</div>\s*<button type="button" class="elemacy-popup__close#s', $html),
            'The bar content is printed inside the box and scroll element, followed by the close button.'
        );
        self::assertDoesNotMatchRegularExpression(
            '#<template class="elemacy-popup-tpl">(?:(?!</template>).)*Default bar marker#s',
            $html,
            'The default bar content is not held back in a template.'
        );

        $capped_root = $this->root($html, $capped);
        self::assertStringContainsString(' hidden', $capped_root, 'A bar with a frequency cap is opened by the engine.');
        self::assertStringNotContainsString('data-elemacy-prerendered', $capped_root);
        self::assertMatchesRegularExpression('#<template class="elemacy-popup-tpl">(?:(?!</template>).)*Capped bar marker#s', $html);

        self::assertStringContainsString(' hidden', $this->root($html, $bottom), 'A bottom bar is opened by the engine.');
    }

    public function testATopBarIsPrintedExactlyOnceMarkedForRelocationWhenTheThemeNeverCallsWpBodyOpen(): void
    {
        $bar = $this->create_popup('No body open bar marker', 'topbar');

        $html = $this->render_host('theme-no-body-open');

        self::assertSame(1, substr_count($html, 'data-elemacy-popup-id="' . $bar . '"'), 'The top bar root is output exactly once: ' . $html);
        self::assertStringContainsString('data-elemacy-relocate="top"', $this->root($html, $bar), 'The footer copy asks the engine to move it to the top of the page.');
    }

    public function testATopBarOnAThemeThatCallsWpBodyOpenIsNotMarkedForRelocation(): void
    {
        $bar = $this->create_popup('Body open bar marker', 'topbar');

        $html = $this->render_host();

        self::assertSame(1, substr_count($html, 'data-elemacy-popup-id="' . $bar . '"'), 'The top bar root is output exactly once.');
        self::assertStringNotContainsString('data-elemacy-relocate', $this->root($html, $bar));
        self::assertLessThan(
            strpos($html, 'generic-native-nav'),
            strpos($html, 'data-elemacy-popup-id="' . $bar . '"'),
            'It is printed above the theme\'s header, where wp_body_open fires.'
        );
    }
}
