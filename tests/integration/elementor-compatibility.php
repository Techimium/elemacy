<?php

namespace Elemacy\Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Task 9.1's own verify clause requires a real integration fixture for every
 * entry in docs/ELEMENTOR-COMPATIBILITY.md, not merely a class_exists() guard.
 * These two scenarios were the genuine gaps the audit found while building
 * that inventory (grepped against the full tests/integration + tests/e2e +
 * tests/php trees before writing this file — neither had any prior coverage,
 * fast-harness included):
 *
 * 1. AcfIconList/AcfGallery/AcfAccordion each `extends` a concrete Elementor
 *    core widget class (Widget_Icon_List/Widget_Image_Gallery/Widget_Accordion)
 *    rather than Widget_Base directly, to reuse its style controls and
 *    render() internals verbatim. That is a materially different, more
 *    version-fragile compatibility surface than the Widget_Base extension
 *    every other Elemacy widget uses: a change to any of those three
 *    concrete widgets' internal register_controls()/get_settings_for_display()
 *    /render() contract in a future Elementor release could break these three
 *    widgets silently, with no class_exists() guard able to catch it (the
 *    classes would still exist; their internals would just have moved).
 * 2. TemplateAssetsRegistrar::get_related_posts() is Elemacy's only consumer
 *    of Elementor's `elementor/document/related_posts` filter.
 */
final class ElementorCompatibilityTest extends IntegrationTestCase
{
    private string $fieldGroup = '';
    private string $fieldKey = '';

    /** @var array<int, int> */
    private array $attachments = [];

    /** @var array<int, int> Plain WP posts created as the "current content" carrying ACF field values. */
    private array $contentPosts = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('acf_add_local_field_group')) {
            self::markTestSkipped('Native ACF is required for the ACF-widget compatibility fixtures.');
        }

        // Elementor's optional element-cache module (Core\Modules\ElementCache)
        // defers any is_dynamic_content() widget — all three ACF widgets under
        // test — into a `[elementor-element k="…" data="…"]` shortcode on
        // first render, re-expanded only once that module's shortcode has
        // actually been registered. Whether that has happened yet by a given
        // point in the request depends on Elementor's own experiment-default
        // bookkeeping, not on anything Elemacy controls, and is unrelated to
        // what this fixture verifies (whether these widgets render through
        // the concrete Elementor widget class they extend). Disabling it
        // here is the same kind of irrelevant-external-factor exclusion the
        // real browser runner already applies to Google Fonts fetching.
        update_option('elementor_element_cache_ttl', 'disable');
    }

    protected function tearDown(): void
    {
        try {
            delete_option('elementor_element_cache_ttl');
            if ('' !== $this->fieldGroup) {
                acf_remove_local_field_group($this->fieldGroup);
            }
            foreach ($this->attachments as $attachment_id) {
                wp_delete_attachment($attachment_id, true);
            }
            foreach ($this->contentPosts as $post_id) {
                wp_delete_post($post_id, true);
            }
        } finally {
            $this->fieldGroup = $this->fieldKey = '';
            $this->attachments = $this->contentPosts = [];
            parent::tearDown();
        }
    }

    /**
     * A real WP 'post' — deliberately NOT the same post as the Elementor
     * template document these tests render. Elementor's own
     * get_builder_content_for_display() refuses to render a document whose ID
     * equals get_the_ID() (its documented recursion guard), so the "current
     * post" a real Theme Builder/single template renders against is always a
     * different post than the template document itself; these widgets' ACF
     * field resolution (AcfRepeaterResolver::resolve(), no explicit post ID)
     * relies on exactly that ambient "current post", matching how they behave
     * embedded in a real single/archive template applied to real content.
     */
    private function createContentPost(): int
    {
        $id = wp_insert_post([
            'post_type' => 'post',
            'post_title' => 'Elemacy compatibility fixture content post',
            'post_status' => 'publish',
        ], true);
        if (is_wp_error($id)) {
            throw new \RuntimeException($id->get_error_message());
        }
        $this->contentPosts[] = (int) $id;

        return (int) $id;
    }

    /**
     * Registers a real ACF repeater field and attaches its value to
     * $content_post_id (see createContentPost()) — never to the document
     * being rendered.
     *
     * @param array<int, array<string, mixed>> $subFields
     * @param array<int, array<string, mixed>> $rows
     */
    private function registerRepeaterField(int $content_post_id, string $suffix, array $subFields, array $rows): void
    {
        $this->fieldGroup = 'group_elemacy_compat_' . $suffix;
        $this->fieldKey = 'field_elemacy_compat_' . $suffix;
        $field_name = 'elemacy_compat_rows_' . $suffix;

        acf_add_local_field_group([
            'key' => $this->fieldGroup,
            'title' => 'Elemacy compatibility fixture rows',
            'fields' => [[
                'key' => $this->fieldKey,
                'name' => $field_name,
                'label' => 'Rows',
                'type' => 'repeater',
                'sub_fields' => $subFields,
            ]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);

        if (!update_field($this->fieldKey, $rows, $content_post_id)) {
            throw new \RuntimeException('Native ACF repeater data could not be saved for the compatibility fixture.');
        }
    }

    /**
     * @param string $widget_type
     * @param array<string, mixed> $settings
     */
    private function widgetElement(string $widget_type, array $settings): array
    {
        return [[
            'id' => 'eacc001',
            'elType' => 'container',
            'settings' => [],
            'elements' => [[
                'id' => 'eacc002',
                'elType' => 'widget',
                'widgetType' => $widget_type,
                'settings' => $settings,
                'elements' => [],
            ]],
        ]];
    }

    /**
     * Renders $document_id (the template) while the ambient "current post" is
     * $content_post_id — a different post, per createContentPost()'s own
     * docblock.
     */
    private function renderWithContentPost(int $document_id, int $content_post_id): string
    {
        global $post;
        $previous = $post;

        $post = get_post($content_post_id); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below, same idiom as other integration fixtures.
        setup_postdata($post);

        try {
            return (new \Elemacy\Core\Rendering\TemplateRenderer())->render($document_id);
        } finally {
            $post = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- see above.
            if ($post instanceof \WP_Post) {
                setup_postdata($post);
            } else {
                wp_reset_postdata();
            }
        }
    }

    public function testAcfIconListRendersThroughTheConcreteElementorWidgetItExtends(): void
    {
        $content_post_id = $this->createContentPost();
        $field_name = 'elemacy_compat_rows_icon_list';
        $id = $this->documents->create(
            'Compat: ACF Icon List',
            'section',
            'publish',
            $this->widgetElement('elemacy-acf-icon-list', [
                'repeater_field' => $field_name,
                'sub_field' => 'label',
            ])
        );

        $this->registerRepeaterField($content_post_id, 'icon_list', [
            ['key' => 'field_elemacy_compat_icon_label', 'name' => 'label', 'label' => 'Label', 'type' => 'text'],
        ], [
            ['label' => 'First icon list row'],
            ['label' => 'Second icon list row'],
        ]);

        $html = $this->renderWithContentPost($id, $content_post_id);

        self::assertStringContainsString('elementor-icon-list-items', $html, 'Widget_Icon_List::render() did not run.');
        self::assertStringContainsString('First icon list row', $html);
        self::assertStringContainsString('Second icon list row', $html);
    }

    public function testAcfAccordionRendersThroughTheConcreteElementorWidgetItExtends(): void
    {
        $content_post_id = $this->createContentPost();
        $field_name = 'elemacy_compat_rows_accordion';
        $id = $this->documents->create(
            'Compat: ACF Accordion',
            'section',
            'publish',
            $this->widgetElement('elemacy-acf-accordion', [
                'repeater_field' => $field_name,
                'title_sub_field' => 'question',
                'content_sub_field' => 'answer',
            ])
        );

        $this->registerRepeaterField($content_post_id, 'accordion', [
            ['key' => 'field_elemacy_compat_accordion_q', 'name' => 'question', 'label' => 'Question', 'type' => 'text'],
            ['key' => 'field_elemacy_compat_accordion_a', 'name' => 'answer', 'label' => 'Answer', 'type' => 'text'],
        ], [
            ['question' => 'Compat accordion question', 'answer' => 'Compat accordion answer'],
        ]);

        $html = $this->renderWithContentPost($id, $content_post_id);

        self::assertStringContainsString('elementor-accordion-item', $html, 'Widget_Accordion::render() did not run.');
        self::assertStringContainsString('Compat accordion question', $html);
        self::assertStringContainsString('Compat accordion answer', $html);
    }

    public function testAcfGalleryRendersThroughTheConcreteElementorWidgetItExtends(): void
    {
        $content_post_id = $this->createContentPost();

        $attachment_id = wp_insert_attachment([
            'post_title' => 'Elemacy compatibility fixture attachment',
            'post_mime_type' => 'image/jpeg',
            'post_status' => 'inherit',
        ], false);
        self::assertIsInt($attachment_id, 'Native attachment creation failed.');
        $this->attachments[] = $attachment_id;

        $field_name = 'elemacy_compat_rows_gallery';
        $id = $this->documents->create(
            'Compat: ACF Gallery',
            'section',
            'publish',
            $this->widgetElement('elemacy-acf-gallery', [
                'wp_gallery' => $field_name,
            ])
        );

        $this->registerRepeaterField($content_post_id, 'gallery', [
            ['key' => 'field_elemacy_compat_gallery_id', 'name' => 'id', 'label' => 'Attachment ID', 'type' => 'number'],
        ], [
            ['id' => $attachment_id],
        ]);

        $html = $this->renderWithContentPost($id, $content_post_id);

        self::assertStringContainsString('elementor-image-gallery', $html, 'Widget_Image_Gallery::render() did not run.');
    }

    public function testRelatedPostsFilterFeedsRegisteredIdsIntoElementorsGlobalClassesDependencyTracking(): void
    {
        $host = $this->documents->create('Compat: related-posts host');
        $registered = $this->documents->create('Compat: related-posts dependency');

        $registrar = new \Elemacy\Core\Rendering\TemplateAssetsRegistrar();
        $registrar->register_hooks();
        $registrar->register($registered);

        global $post;
        $previous_post = $post;
        $post = get_post($host); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
        setup_postdata($post);

        try {
            $related_for_host = apply_filters('elementor/document/related_posts', [], $host);
            $related_for_other = apply_filters('elementor/document/related_posts', [], $registered + 1000000);
        } finally {
            remove_filter('elementor/document/related_posts', [$registrar, 'get_related_posts'], 10);
            $post = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- see above.
            if ($post instanceof \WP_Post) {
                setup_postdata($post);
            } else {
                wp_reset_postdata();
            }
        }

        self::assertContains($registered, $related_for_host, 'A registered template id must feed elementor/document/related_posts for the currently-queried host.');
        self::assertNotContains($registered, $related_for_other, 'A registered template id must not be attributed to an unrelated queried post id.');
    }
}
