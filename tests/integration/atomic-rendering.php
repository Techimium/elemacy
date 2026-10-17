<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Rendering\LoopRenderer;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

/**
 * Documents authored from genuinely atomic elements (e-flexbox, e-heading,
 * e-button, each with its own local style) driven through the real atomic
 * pipeline: Elementor's element traversal, style schema and Styles_Renderer,
 * not a stub. The per-item color is bound to a dynamic tag
 * (fixtures/mu-plugins/atomic-dynamic-color-tag.php) that reads each post's
 * own meta.
 */
final class AtomicRenderingTest extends IntegrationTestCase
{
    private array $owned_posts = [];
    private $previous_cache_ttl;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Plugin::instance()->elements_manager->get_element_types('e-flexbox')) {
            self::markTestSkipped('The installed Elementor does not register atomic elements (e-flexbox).');
        }

        // Elementor's Element Cache defers an element it considers dynamic
        // into a placeholder expanded later; unrelated to what is checked here.
        $this->previous_cache_ttl = get_option('elementor_element_cache_ttl');
        update_option('elementor_element_cache_ttl', 'disable');
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->owned_posts as $id) {
                wp_delete_post($id, true);
            }
            if (false === $this->previous_cache_ttl) {
                delete_option('elementor_element_cache_ttl');
            } else {
                update_option('elementor_element_cache_ttl', $this->previous_cache_ttl);
            }
        } finally {
            parent::tearDown();
        }
    }

    /** One local style class with the given props. */
    private static function style(string $class, array $props): array
    {
        return [$class => [
            'id' => $class, 'label' => 'local', 'type' => 'class',
            'variants' => [['meta' => ['breakpoint' => 'desktop', 'state' => null], 'props' => $props, 'custom_css' => null]],
        ]];
    }

    /**
     * A text value in the shape the installed Elementor accepts for this
     * atomic widget prop: Elementor changed it from an `html-v3` object to a
     * plain `escaped-html` string, and a document saved in the wrong shape is
     * rejected (`invalid_value`).
     */
    private static function text(string $widget_type, string $prop, string $text): array
    {
        $widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types($widget_type);
        $accepted = array_keys($widget::get_props_schema()[$prop]->get_prop_types());

        if (in_array('escaped-html', $accepted, true)) {
            return ['$$type' => 'escaped-html', 'value' => $text];
        }

        return ['$$type' => 'html-v3', 'value' => ['content' => ['$$type' => 'string', 'value' => $text], 'children' => []]];
    }

    private static function widget(string $id, string $type, string $class, array $settings, array $props): array
    {
        return [
            'id' => $id, 'elType' => 'widget', 'widgetType' => $type, 'isInner' => false,
            'settings' => ['classes' => ['$$type' => 'classes', 'value' => [$class]]] + $settings,
            'styles' => self::style($class, $props),
            'elements' => [], 'interactions' => [], 'editor_settings' => [], 'version' => '0.0',
        ];
    }

    /**
     * A styled e-flexbox holding a styled e-heading and e-button. With
     * $dynamic_heading, a second e-heading whose text color comes from the
     * fixture color tag; it is the only element with a dynamic style, so it
     * alone moves to the per-item pass.
     */
    private static function atomic_tree(string $prefix, string $marker, bool $dynamic_heading): array
    {
        $children = [
            self::widget($prefix . '1', 'e-heading', 'e-' . $prefix . '1a', ['title' => self::text('e-heading', 'title', $marker . ' heading')], ['color' => ['$$type' => 'color', 'value' => '#123401']]),
            self::widget($prefix . '2', 'e-button', 'e-' . $prefix . '2a', ['text' => self::text('e-button', 'text', $marker . ' button')], ['color' => ['$$type' => 'color', 'value' => '#123402']]),
        ];

        if ($dynamic_heading) {
            $children[] = self::widget($prefix . '3', 'e-heading', 'e-' . $prefix . '3d', ['title' => self::text('e-heading', 'title', $marker . ' dynamic heading')], [
                'color' => ['$$type' => 'dynamic', 'value' => ['name' => 'elemacy-fixture-item-color', 'group' => 'site', 'settings' => []]],
            ]);
        }

        return [[
            'id' => $prefix . '0', 'elType' => 'e-flexbox', 'isInner' => false,
            'settings' => ['classes' => ['$$type' => 'classes', 'value' => ['e-' . $prefix . '0a']]],
            'styles' => self::style('e-' . $prefix . '0a', ['padding' => ['$$type' => 'size', 'value' => ['size' => 13, 'unit' => 'px']]]),
            'elements' => $children, 'interactions' => [], 'editor_settings' => [], 'version' => '0.0',
        ]];
    }

    private function post(string $title, string $color = ''): int
    {
        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $title . ' body.'], true);
        self::assertIsInt($id);
        $this->owned_posts[] = $id;
        if ('' !== $color) {
            update_post_meta($id, 'elemacy_atomic_color', $color);
        }

        return $id;
    }

    public function testALoopTemplateEmitsItsAtomicBaseCssOnceAndScopesPerItemAtomicCssToEachItem(): void
    {
        $template = $this->documents->create('Atomic loop item', 'loop', 'publish', self::atomic_tree('eal', 'Atomic loop', true));

        $stored = json_decode((string) get_post_meta($template, '_elementor_data', true), true);
        self::assertArrayHasKey('e-eal3d', $stored[0]['elements'][2]['styles'] ?? [], 'Sanity: Elementor must accept the dynamic style on save, or there is no per-item CSS to test.');

        $colors = ['#aa0001', '#aa0002', '#aa0003'];
        $posts = array_map(fn(string $color): int => $this->post('Atomic item ' . $color, $color), $colors);

        $renderer = new LoopRenderer();
        ob_start();
        try {
            foreach ($posts as $occurrence => $post_id) {
                self::assertTrue($renderer->render_item($template, new PostLoopItem(get_post($post_id)), 'atomic-placement', 'posts', $occurrence));
            }
            $output = (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }

        foreach (['e-flexbox-base' => 3, 'e-heading-base' => 6, 'e-button-base' => 3] as $atomic => $count) {
            self::assertSame($count, substr_count($output, $atomic), "Each item renders real atomic elements ($atomic): " . $output);
        }

        // Base: one stylesheet for the template, holding every static atomic style and none of the dynamic one.
        self::assertSame(1, preg_match_all('#<style id="elemacy-loop-base-' . $template . '">(.*?)</style>#s', $output, $base), 'Atomic base CSS is emitted exactly once for the template: ' . $output);
        foreach (['.e-eal0a{padding:13px;}', '.e-eal1a{color:#123401;}', '.e-eal2a{color:#123402;}'] as $rule) {
            self::assertStringContainsString($rule, $base[1][0], 'The base CSS carries each static atomic style.');
        }
        self::assertStringNotContainsString('e-eal3d', $base[1][0], 'The dynamic style is never in the shared base CSS.');

        // Per item: its own stylesheet, scoped to its own wrapper, carrying its own post's color.
        preg_match_all('#<style id="elemacy-loop-item-([a-z0-9-]+)">\.elemacy-loop-item-\1 \.e-eal3d\{color:(\#[0-9a-f]{6});\}</style>#', $output, $items, PREG_SET_ORDER);
        self::assertCount(3, $items, 'Each item gets its own scoped atomic stylesheet: ' . $output);
        self::assertSame($colors, array_column($items, 2), "Each item's CSS carries that item's own value, in render order.");
        self::assertCount(3, array_unique(array_column($items, 1)), 'Every item has its own scope.');
        foreach ($items as [, $scope]) {
            self::assertSame(1, substr_count($output, 'class="elemacy-loop-item elemacy-loop-item-' . $scope . '"'), 'Each scope names exactly one rendered item wrapper.');
        }
    }

    public function testAnAtomicHeaderDeliversItsAtomicStylesheetOnAPageItRendersOn(): void
    {
        $header = $this->documents->create('Atomic header', 'header', 'publish', self::atomic_tree('eah', 'Atomic header', false));
        $post = $this->post('Atomic header host');

        // A fresh process: Elementor emits atomic stylesheets once per process.
        $html = ThemePageRequest::render('theme-generic-chrome', get_permalink($post))['html'];

        self::assertSame(1, substr_count($html, 'Atomic header heading'), 'The atomic header renders exactly once: ' . $html);
        self::assertStringContainsString('e-heading-base', $html, 'The header is rendered from real atomic elements.');

        $css = $this->delivered_css($html, $header);
        foreach (['.e-eah0a{padding:13px;}', '.e-eah1a{color:#123401;}', '.e-eah2a{color:#123402;}'] as $rule) {
            self::assertStringContainsString($rule, $css, 'The page delivers the header\'s atomic stylesheet (' . $rule . '): ' . $html);
        }
    }

    /**
     * Every stylesheet Elementor delivered for $id's atomic styles: inline
     * <style> blocks, and the contents of linked local-<id>-* files.
     */
    private function delivered_css(string $html, int $id): string
    {
        $css = '';
        preg_match_all('#<style[^>]*>(.*?)</style>#s', $html, $inline);
        $css .= implode("\n", $inline[1]);

        preg_match_all('#<link[^>]+id=[\'"](?:elemacy-)?local-' . $id . '-[^\'"]+[\'"][^>]*>#', $html, $links);
        foreach ($links[0] as $link) {
            if (preg_match('#href=[\'"]([^\'"]+)[\'"]#', $link, $href)) {
                $path = wp_upload_dir()['basedir'] . substr(strtok(html_entity_decode($href[1]), '?'), strlen(wp_upload_dir()['baseurl']));
                $css .= is_file($path) ? (string) file_get_contents($path) : '';
            }
        }

        return $css;
    }
}
