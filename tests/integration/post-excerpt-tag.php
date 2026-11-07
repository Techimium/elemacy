<?php

namespace Elemacy\Tests\Integration;

use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Elementor saves dynamic tag settings without their defaults, so a Post
 * Excerpt tag inserted and left alone arrives with a blank Excerpt Length.
 * That must render the whole excerpt, not an empty string.
 */
final class PostExcerptTagTest extends IntegrationTestCase
{
    private const EXCERPT = 'Elemacy excerpt probe one two three';

    private int $page = 0;

    /** @var mixed */
    private $previous_cache_ttl;

    protected function setUp(): void
    {
        parent::setUp();

        // Element caching would emit the dynamic widget as an
        // [elementor-element] placeholder instead of rendering the tag.
        $this->previous_cache_ttl = get_option('elementor_element_cache_ttl');
        update_option('elementor_element_cache_ttl', 'disable');
    }

    protected function tearDown(): void
    {
        try {
            wp_reset_query();

            if ($this->page) {
                wp_delete_post($this->page, true);
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

    private function render_page_with_excerpt_tag(array $tag_settings): string
    {
        $this->page = (int) wp_insert_post([
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => 'Post Excerpt tag probe',
            'post_excerpt' => self::EXCERPT,
        ]);

        $tag = '[elementor-tag id="excerpt1" name="elemacy-post-excerpt" settings="' . rawurlencode((string) wp_json_encode((object) $tag_settings)) . '"]';
        $data = [[
            'id' => 'probe1',
            'elType' => 'container',
            'settings' => [],
            'elements' => [[
                'id' => 'probe2',
                'elType' => 'widget',
                'widgetType' => 'heading',
                'settings' => ['title' => 'static fallback', '__dynamic__' => ['title' => $tag]],
                'elements' => [],
            ]],
        ]];

        update_post_meta($this->page, '_elementor_edit_mode', 'builder');
        update_post_meta($this->page, '_elementor_version', ELEMENTOR_VERSION);
        update_post_meta($this->page, '_elementor_data', wp_slash((string) wp_json_encode($data)));

        query_posts(['p' => $this->page, 'post_type' => 'page']);
        the_post();

        return (string) Plugin::$instance->frontend->get_builder_content($this->page, true);
    }

    public function testATagLeftAtItsDefaultsRendersTheWholeExcerpt(): void
    {
        $html = $this->render_page_with_excerpt_tag([]);

        self::assertStringContainsString(self::EXCERPT, $html, 'A blank Excerpt Length must render the whole excerpt.');
    }

    public function testAnExcerptLengthTrimsToThatManyWords(): void
    {
        $html = $this->render_page_with_excerpt_tag(['max_length' => 3]);

        self::assertStringContainsString('Elemacy excerpt probe', $html);
        self::assertStringNotContainsString('probe one', $html, 'Words beyond Excerpt Length are dropped.');
    }
}
