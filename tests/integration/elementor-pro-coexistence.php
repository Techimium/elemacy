<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\LibraryDocument;
use Elemacy\Tests\Integration\Fixtures\ThemePageRequest;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemePageRequest.php';

/**
 * With Elementor Pro's Theme Builder active, each location is served by
 * exactly one builder: Elementor Pro's wherever one of its templates applies,
 * Elemacy's everywhere else, and the page keeps a single document shell.
 *
 * Runs in the Elementor Pro profile only (setup.py --elementor-pro-plugin, a
 * paid plugin the owner provides); elsewhere it skips with that reason.
 */
final class ElementorProCoexistenceTest extends IntegrationTestCase
{
    private const THEME = 'theme-generic-chrome';
    private const PRO_HEADER = 'Elementor Pro coexistence header';
    private const PRO_SINGLE = 'Elementor Pro coexistence single';
    private const HEADER = 'Elemacy coexistence header';
    private const FOOTER = 'Elemacy coexistence footer';
    private const SINGLE = 'Elemacy coexistence single';

    /** @var int[] */
    private array $owned = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('elementor_location_exits') || !class_exists('\ElementorPro\Modules\ThemeBuilder\Module')) {
            self::markTestSkipped('Elementor Pro is not active; provision with setup.py --elementor-pro-plugin to run the coexistence profile.');
        }
    }

    protected function tearDown(): void
    {
        try {
            foreach ($this->owned as $id) {
                wp_delete_post($id, true);
            }
            $this->owned = [];
            \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->get_cache()->regenerate();
        } finally {
            parent::tearDown();
        }
    }

    /** An Elementor Pro theme template of $type with Pro conditions, e.g. ['include/general']. */
    private function pro_template(string $type, string $marker, array $conditions): int
    {
        $document = \Elementor\Plugin::instance()->documents->create($type, [
            'post_title' => $marker,
            'post_status' => 'publish',
        ]);
        self::assertNotWPError($document);
        $id = $document->get_main_id();
        $this->owned[] = $id;
        $document->save(['elements' => LibraryDocument::heading($marker), 'settings' => ['post_status' => 'publish']]);

        $rules = array_map(static function (string $condition): array {
            $parts = explode('/', $condition);

            return ['type' => $parts[0], 'name' => $parts[1] ?? '', 'sub_name' => $parts[2] ?? '', 'sub_id' => $parts[3] ?? ''];
        }, $conditions);
        self::assertTrue((bool) \ElementorPro\Modules\ThemeBuilder\Module::instance()->get_conditions_manager()->save_conditions($id, $rules));

        return $id;
    }

    private static function assertNotWPError($value): void
    {
        self::assertFalse(is_wp_error($value), is_wp_error($value) ? $value->get_error_message() : '');
    }

    private function post(string $type): int
    {
        $id = wp_insert_post(['post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Coexistence ' . $type, 'post_content' => 'Coexistence body.'], true);
        self::assertIsInt($id);
        $this->owned[] = $id;

        return $id;
    }

    private static function assertSingleDocumentShell(string $html): void
    {
        self::assertSame(1, preg_match_all('/<html[ >]/', $html), 'Exactly one <html> tag: ' . $html);
        self::assertSame(1, preg_match_all('/<head[ >]/', $html), 'Exactly one <head> tag.');
        self::assertSame(1, preg_match_all('/<body[ >]/', $html), 'Exactly one <body> tag.');
    }

    public function testWhenBothHaveAHeaderOnlyElementorProsRendersInOneDocumentShell(): void
    {
        $this->pro_template('header', self::PRO_HEADER, ['include/general']);
        $this->documents->create_everywhere(self::HEADER, 'header', 'publish', LibraryDocument::heading(self::HEADER));

        $html = ThemePageRequest::render(self::THEME, get_permalink($this->post('post')))['html'];

        self::assertSame(1, substr_count($html, self::PRO_HEADER), "Elementor Pro's header renders once: " . $html);
        self::assertSame(0, substr_count($html, self::HEADER), 'Elemacy leaves the header to Elementor Pro.');
        self::assertSingleDocumentShell($html);
    }

    public function testElemacyStillServesTheSideElementorProDoesNot(): void
    {
        $this->pro_template('header', self::PRO_HEADER, ['include/general']);
        $this->documents->create_everywhere(self::FOOTER, 'footer', 'publish', LibraryDocument::heading(self::FOOTER));

        $html = ThemePageRequest::render(self::THEME, get_permalink($this->post('post')))['html'];

        self::assertSame(1, substr_count($html, self::PRO_HEADER), "Elementor Pro's header renders once: " . $html);
        self::assertSame(1, substr_count($html, self::FOOTER), "Elemacy's footer renders once.");
        self::assertStringNotContainsString('Generic native footer', $html, "The theme's own footer is replaced.");
        self::assertSingleDocumentShell($html);
    }

    public function testContentLocationsSplitByWhichBuilderHasAMatchingTemplate(): void
    {
        $this->pro_template('single-post', self::PRO_SINGLE, ['include/singular/post']);
        $this->documents->create_everywhere(self::SINGLE, 'single', 'publish', LibraryDocument::heading(self::SINGLE));

        $post = ThemePageRequest::render(self::THEME, get_permalink($this->post('post')))['html'];
        $page = ThemePageRequest::render(self::THEME, get_permalink($this->post('page')))['html'];

        self::assertSame(1, substr_count($post, self::PRO_SINGLE), "A post uses Elementor Pro's single template: " . $post);
        self::assertSame(0, substr_count($post, self::SINGLE), "Elemacy's Single template yields on posts.");
        self::assertSame(1, substr_count($page, self::SINGLE), "A page uses Elemacy's Single template: " . $page);
        self::assertSame(0, substr_count($page, self::PRO_SINGLE));
        self::assertSingleDocumentShell($post);
        self::assertSingleDocumentShell($page);
    }
}
