<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Tests\Integration\Fixtures\RequestSimulator;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/RequestSimulator.php';

/**
 * When a loop cannot be shown to visitors, a user who can edit the page sees
 * an inline reason in its place; a guest sees nothing at all; the editor
 * preview still renders the author's saved settings unfiltered.
 */
final class LoopIneligibilityNoticeTest extends IntegrationTestCase
{
    private RequestSimulator $request;
    private array $posts = [];
    private int $page = 0;
    private int $admin = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = get_current_user_id();
        $this->request = new RequestSimulator();

        $suffix = bin2hex(random_bytes(4));
        $post = wp_insert_post([
            'post_type' => 'post', 'post_status' => 'publish',
            'post_title' => 'Notice fixture ' . $suffix, 'post_content' => 'Fixture.',
        ], true);
        if (is_wp_error($post)) {
            throw new \RuntimeException($post->get_error_message());
        }
        $this->posts[] = (int) $post;

        $draft = $this->documents->create('Draft loop ' . $suffix, 'loop', 'draft');
        $settings = ['data_source' => 'posts', 'post_type' => 'post', 'posts_per_page' => 1, 'template_id' => $draft];

        $page = Plugin::instance()->documents->create('wp-page', [
            'post_type' => 'page', 'post_title' => 'Notice host ' . $suffix, 'post_status' => 'draft',
        ]);
        if (is_wp_error($page)) {
            throw new \RuntimeException($page->get_error_message());
        }
        $this->page = $page->get_main_id();
        $this->posts[] = $this->page;
        $elements = [[
            'id' => 'eac7100', 'elType' => 'container', 'settings' => [],
            'elements' => [
                ['id' => 'eac7101', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid', 'settings' => $settings, 'elements' => []],
                ['id' => 'eac7102', 'elType' => 'widget', 'widgetType' => 'elemacy-loop-carousel', 'settings' => $settings, 'elements' => []],
            ],
        ]];
        if (!$page->save(['elements' => $elements, 'settings' => ['post_status' => 'publish']])) {
            throw new \RuntimeException('Could not save the host page.');
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->request->restore();
            foreach (array_reverse($this->posts) as $id) {
                wp_delete_post($id, true);
            }
        } finally {
            parent::tearDown();
        }
    }

    /** @dataProvider widgets */
    public function testEditorSeesWhyGuestsSeeNothing(string $widget_id): void
    {
        $this->request->visit(get_permalink($this->page));

        $html = $this->render($widget_id);
        self::assertStringContainsString('elemacy-loop-notice', $html, 'An editor must see the inline notice: ' . $html);
        self::assertStringContainsString('is not published', $html);
        self::assertStringNotContainsString('elemacy-loop-item', $html);

        wp_set_current_user(0);
        $html = $this->render($widget_id);
        self::assertStringNotContainsString('elemacy-loop-notice', $html, 'A guest must not see the notice: ' . $html);
        self::assertStringNotContainsString('not published', $html);
        self::assertStringNotContainsString('elemacy-loop-item', $html);

        wp_set_current_user($this->admin);
        Plugin::instance()->editor->set_edit_mode(true);
        try {
            $html = $this->render($widget_id);
        } finally {
            Plugin::instance()->editor->set_edit_mode(false);
        }
        self::assertStringContainsString('elemacy-loop-item', $html, 'The editor preview must still render the saved draft-template reference: ' . $html);
        self::assertStringNotContainsString('elemacy-loop-notice', $html);
    }

    public function widgets(): array
    {
        return ['grid' => ['eac7101'], 'carousel' => ['eac7102']];
    }

    private function render(string $widget_id): string
    {
        $document = Plugin::$instance->documents->get_doc_for_frontend($this->page);
        self::assertNotNull($document);
        Plugin::$instance->documents->switch_to_document($document);

        try {
            $raw = $this->findWidget($document->get_elements_data(), $widget_id);
            self::assertNotNull($raw, 'Fixture widget not found.');
            $widget = Plugin::$instance->elements_manager->create_element_instance($raw);
            $method = new \ReflectionMethod($widget, 'render');
            $method->setAccessible(true);
            ob_start();
            try {
                $method->invoke($widget);
            } finally {
                $html = ob_get_clean();
            }
        } finally {
            Plugin::$instance->documents->restore_document();
        }

        return $html;
    }

    private function findWidget(array $elements, string $widget_id): ?array
    {
        foreach ($elements as $element) {
            if (($element['id'] ?? null) === $widget_id) {
                return $element;
            }
            if (!empty($element['elements'])) {
                $found = $this->findWidget($element['elements'], $widget_id);
                if (null !== $found) {
                    return $found;
                }
            }
        }

        return null;
    }
}
