<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Core\Constants\OptionKeys;
use Elemacy\Core\Container;
use Elemacy\Core\Migrator;
use Elemacy\Modules\ThemeBuilder\Documents\FooterDocument;
use Elemacy\Modules\ThemeBuilder\Documents\HeaderDocument;
use Elemacy\TemplateLibrary\DocumentWriter;
use Elemacy\TemplateLibrary\LibraryPostType;
use Elemacy\Tests\Integration\Fixtures\ThemeLifecycleHarness;
use Elementor\Plugin;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/ThemeLifecycleHarness.php';

/**
 * Header and Footer templates edit on their own chrome documents, which offer
 * only the settings that affect site chrome, and stored document types that no
 * longer match the registry are repaired by a schema step.
 *
 * The end-to-end case here renders `theme-chrome-assets`, a fixture theme no
 * other scenario uses. A classic theme's header.php is loaded with
 * require_once, so in one long-lived PHPUnit process only the first render of
 * a given theme actually executes it — and therefore only that render produces
 * a real `<head>` with wp_head()'s output. Sharing `elemacy-fixture` with
 * theme-lifecycle.php would leave whichever ran second asserting against a
 * document with no head at all.
 */
final class ChromeDocumentsTest extends IntegrationTestCase
{
    /** Controls PageBase offers that mean nothing for site chrome. */
    private const PAGE_LEVEL_CONTROLS = [
        'hide_title',
        'post_featured_image',
        'post_excerpt',
        'menu_order',
        'comment_status',
        'template',
        'margin',
        'padding',
        'background_background',
    ];

    private array $previous_schema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previous_schema = [
            'version' => get_option(OptionKeys::SCHEMA_VERSION),
            'cursor' => get_option(Migrator::RECONCILE_CURSOR_OPTION),
        ];
    }

    protected function tearDown(): void
    {
        try {
            foreach (['version' => OptionKeys::SCHEMA_VERSION, 'cursor' => Migrator::RECONCILE_CURSOR_OPTION] as $key => $option) {
                if (false === $this->previous_schema[$key]) {
                    delete_option($option);
                    continue;
                }

                update_option($option, $this->previous_schema[$key], false);
            }
        } finally {
            parent::tearDown();
        }
    }

    private function writer(): DocumentWriter
    {
        return Container::instance()->make(DocumentWriter::class);
    }

    public function testElementorRegistersTheChromeDocumentTypes(): void
    {
        $registered = Plugin::instance()->documents->get_document_types();

        self::assertArrayHasKey('elemacy_header', $registered);
        self::assertArrayHasKey('elemacy_footer', $registered);
        self::assertSame(HeaderDocument::class, $registered['elemacy_header']);
        self::assertSame(FooterDocument::class, $registered['elemacy_footer']);
    }

    public function testTheRegistryResolvesHeaderAndFooterToTheChromeDocuments(): void
    {
        self::assertSame('elemacy_header', $this->writer()->document_type('header'));
        self::assertSame('elemacy_footer', $this->writer()->document_type('footer'));
    }

    public function testAChromeDocumentOffersNoPageLevelSettings(): void
    {
        foreach (['header', 'footer'] as $type) {
            $id = $this->documents->create('Chrome controls ' . $type, $type, 'publish');
            $document = Plugin::instance()->documents->get($id, false);

            self::assertNotNull($document, 'Elementor did not open a document for the ' . $type . ' template.');

            $controls = $document->get_controls();

            foreach (self::PAGE_LEVEL_CONTROLS as $control) {
                self::assertArrayNotHasKey(
                    $control,
                    $controls,
                    sprintf('A %s template must not offer the page-level control "%s".', $type, $control)
                );
            }

            // What a chrome template does need: its own name and publication state.
            self::assertArrayHasKey('post_title', $controls);
            self::assertArrayHasKey('post_status', $controls);
        }
    }

    public function testACreatedChromeTemplateCarriesTheChromeDocumentType(): void
    {
        foreach (['header' => 'elemacy_header', 'footer' => 'elemacy_footer'] as $type => $document_type) {
            $id = $this->documents->create('Chrome created ' . $type, $type, 'publish');

            self::assertSame($document_type, get_post_meta($id, '_elementor_template_type', true));
            self::assertSame($type, get_post_meta($id, '_elemacy_template_type', true));
        }
    }

    public function testUpdatingAChromeTemplateKeepsItsChromeDocumentType(): void
    {
        $id = $this->documents->create('Chrome updated', 'header', 'publish');

        $this->writer()->update($id, 'theme', ['post_title' => 'Chrome updated again'], 'header');

        self::assertSame('elemacy_header', get_post_meta($id, '_elementor_template_type', true));
        self::assertSame('Chrome updated again', get_post($id)->post_title);
    }

    public function testDuplicatingAChromeTemplateGivesTheCopyTheChromeDocumentType(): void
    {
        $id = $this->documents->create('Chrome duplicated', 'header', 'publish');

        $copy = Container::instance()
            ->make(\Elemacy\TemplateLibrary\DocumentDuplicator::class)
            ->duplicate($id, 'theme');

        try {
            self::assertSame('elemacy_header', get_post_meta($copy, '_elementor_template_type', true));
            self::assertSame('header', get_post_meta($copy, '_elemacy_template_type', true));
        } finally {
            wp_delete_post($copy, true);
        }
    }

    public function testTheSchemaStepRepairsATemplateStoredWithTheWrongDocumentTypeWithoutTouchingAnythingElse(): void
    {
        $id = $this->documents->create('Chrome needing repair', 'header', 'publish');

        (new \Elemacy\Conditions\ConditionRepository())->save($id, [
            ['type' => 'general/entire_site', 'value' => '', 'operator' => 'include'],
        ]);

        $content = get_post_meta($id, '_elementor_data', true);
        $conditions = get_post_meta($id, '_elemacy_conditions', true);

        // The state a template created before the chrome documents existed is in.
        update_post_meta($id, '_elementor_template_type', 'wp-page');

        $this->run_reconciliation();

        self::assertSame('elemacy_header', get_post_meta($id, '_elementor_template_type', true));
        self::assertSame($content, get_post_meta($id, '_elementor_data', true), 'Authored content must be untouched.');
        self::assertSame($conditions, get_post_meta($id, '_elemacy_conditions', true), 'Display conditions must be untouched.');
        self::assertSame('publish', get_post($id)->post_status, 'Publication state must be untouched.');
    }

    public function testRunningTheSchemaStepASecondTimeChangesNothing(): void
    {
        $id = $this->documents->create('Chrome repaired twice', 'header', 'publish');
        update_post_meta($id, '_elementor_template_type', 'wp-page');

        $this->run_reconciliation();
        $first = get_post_meta($id, '_elementor_template_type', true);
        $revisions = count(get_post_meta($id));

        $this->run_reconciliation();

        self::assertSame('elemacy_header', $first);
        self::assertSame($first, get_post_meta($id, '_elementor_template_type', true));
        self::assertSame($revisions, count(get_post_meta($id)));
    }

    public function testAnAlreadyCorrectTemplateIsNeverWritten(): void
    {
        $id = $this->documents->create('Chrome already correct', 'header', 'publish');

        self::assertSame('elemacy_header', get_post_meta($id, '_elementor_template_type', true));

        $writes = 0;
        $observe = static function ($meta_id, $object_id, $meta_key) use ($id, &$writes) {
            if ((int) $object_id === $id && '_elementor_template_type' === $meta_key) {
                $writes++;
            }
        };

        add_action('updated_post_meta', $observe, 10, 3);
        add_action('added_post_meta', $observe, 10, 3);

        try {
            $this->run_reconciliation();
        } finally {
            remove_action('updated_post_meta', $observe, 10);
            remove_action('added_post_meta', $observe, 10);
        }

        self::assertSame(0, $writes, 'A template whose stored document already matches must not be written.');
    }

    public function testAPopupKeepsItsOwnDocumentTypeThroughReconciliation(): void
    {
        $id = $this->documents->create('Chrome popup untouched', 'popup', 'publish');

        self::assertSame('elemacy_popup', get_post_meta($id, '_elementor_template_type', true));

        $this->run_reconciliation();

        self::assertSame(
            'elemacy_popup',
            get_post_meta($id, '_elementor_template_type', true),
            'Reconciliation must not rewrite a popup to the generic page document.'
        );
    }

    public function testATemplateWhoseTypeTheRegistryDoesNotKnowIsLeftAlone(): void
    {
        $id = $this->documents->create('Chrome unknown type', 'header', 'publish');

        update_post_meta($id, '_elemacy_template_type', 'some_extension_type');
        update_post_meta($id, '_elementor_template_type', 'some_extension_document');

        $this->run_reconciliation();

        self::assertSame('some_extension_document', get_post_meta($id, '_elementor_template_type', true));
    }

    public function testAFreshInstallSkipsTheReconciliationEntirely(): void
    {
        $migrator = new Migrator();

        delete_option(OptionKeys::SCHEMA_VERSION);
        delete_option(Migrator::RECONCILE_CURSOR_OPTION);

        $id = $this->documents->create('Chrome fresh install', 'header', 'publish');
        update_post_meta($id, '_elementor_template_type', 'wp-page');

        $migrator->sync(true);

        self::assertSame(
            'wp-page',
            get_post_meta($id, '_elementor_template_type', true),
            'A fresh install is already shaped like current code and must not run upgrade migrations.'
        );
        self::assertNotFalse(get_option(OptionKeys::SCHEMA_VERSION));
        self::assertFalse(get_option(Migrator::RECONCILE_CURSOR_OPTION), 'No bounded pass should have started.');
    }

    public function testTheSchemaSyncIsQueuedForInitNotRunDuringPluginsLoaded(): void
    {
        $id = $this->documents->create('Chrome scheduled', 'header', 'publish');
        update_post_meta($id, '_elementor_template_type', 'wp-page');

        delete_option(OptionKeys::SCHEMA_VERSION);
        add_option(OptionKeys::SCHEMA_VERSION, Migrator::BASELINE_VERSION, '', false);
        delete_option(Migrator::RECONCILE_CURSOR_OPTION);

        global $wp_filter;

        $before = isset($wp_filter['init'][Migrator::SYNC_PRIORITY])
            ? count($wp_filter['init'][Migrator::SYNC_PRIORITY])
            : 0;

        (new Migrator())->schedule(false);

        self::assertSame(
            'wp-page',
            get_post_meta($id, '_elementor_template_type', true),
            'schedule() must queue the sync, never run it inline.'
        );

        $queued = array_values($wp_filter['init'][Migrator::SYNC_PRIORITY] ?? []);

        self::assertCount($before + 1, $queued, 'schedule() must add exactly one init callback at its own priority.');

        // Invoke only the callback that was just queued. Re-firing the whole
        // `init` action would re-run every other listener, WordPress core's
        // included, which is not what this test is about.
        $callback = end($queued)['function'];

        try {
            $callback();

            self::assertSame('elemacy_header', get_post_meta($id, '_elementor_template_type', true));
        } finally {
            remove_action('init', $callback, Migrator::SYNC_PRIORITY);
        }
    }

    private function run_reconciliation(): void
    {
        delete_option(OptionKeys::SCHEMA_VERSION);
        add_option(OptionKeys::SCHEMA_VERSION, Migrator::BASELINE_VERSION, '', false);
        delete_option(Migrator::RECONCILE_CURSOR_OPTION);

        (new Migrator())->sync(false);
    }

    /**
     * An atomic container carrying its own local style, so a chrome template
     * built from atomic elements exercises the atomic CSS pipeline rather than
     * only the classic one.
     */
    private static function atomic_block(string $element_id, string $style_id, string $color, string $text): array
    {
        return [[
            'id' => $element_id,
            'elType' => 'e-flexbox',
            'settings' => ['classes' => ['$$type' => 'classes', 'value' => [$style_id]]],
            'elements' => [[
                'id' => $element_id . '1',
                'elType' => 'widget',
                'widgetType' => 'heading',
                // A classic control with a styled value, so this one document
                // exercises both pipelines: the classic renderer emits CSS for
                // the heading's colour, the atomic renderer for the container's
                // own style. An atomic-only document has no classic CSS at all,
                // which would make the classic half of this test vacuous.
                'settings' => ['title' => $text, 'header_size' => 'h2', 'title_color' => $color],
                'elements' => [],
            ]],
            'isInner' => false,
            'styles' => [$style_id => [
                'id' => $style_id,
                'label' => 'local',
                'type' => 'class',
                'variants' => [[
                    'meta' => ['breakpoint' => 'desktop', 'state' => null],
                    'props' => ['background' => ['$$type' => 'background', 'value' => ['color' => ['$$type' => 'color', 'value' => $color]]]],
                    'custom_css' => null,
                ]],
            ]],
            'interactions' => [],
            'editor_settings' => [],
            'version' => '0.0',
        ]];
    }

    public function testAChromeTemplateStillRendersItsMarkupAndBothCssPipelinesOnAPageItRendersOn(): void
    {
        if (!Plugin::instance()->elements_manager->get_element_types('e-flexbox')) {
            self::markTestSkipped('The installed Elementor does not register atomic elements (e-flexbox).');
        }

        $header = $this->documents->create(
            'Chrome atomic header',
            'header',
            'publish',
            self::atomic_block('ecd1001', 'e-ecd1001a', '#850202', 'Chrome atomic header marker')
        );
        $footer = $this->documents->create(
            'Chrome atomic footer',
            'footer',
            'publish',
            self::atomic_block('ecd2001', 'e-ecd2001a', '#025a85', 'Chrome atomic footer marker')
        );

        $post_id = wp_insert_post([
            'post_title' => 'Chrome document host',
            'post_type' => 'post',
            'post_status' => 'publish',
            'post_content' => 'Chrome document host body.',
        ], true);

        self::assertIsInt($post_id, is_wp_error($post_id) ? $post_id->get_error_message() : '');

        // Elementor's own Element Cache module defers an element it considers
        // dynamic into an [elementor-element] placeholder that only expands
        // once that module's shortcode has run. It is unrelated to what this
        // test checks (that a chrome document's markup and both CSS pipelines
        // reach the host page), so it is excluded the same way
        // elementor-compatibility.php already excludes it.
        $previous_cache_ttl = get_option('elementor_element_cache_ttl');
        update_option('elementor_element_cache_ttl', 'disable');

        $harness = new ThemeLifecycleHarness();

        try {
            $harness->switch_theme('theme-chrome-assets');
            $html = $harness->render(get_permalink((int) $post_id));

            self::assertSame(1, substr_count($html, 'Chrome atomic header marker'), 'The Header template must render exactly once.');
            self::assertSame(1, substr_count($html, 'Chrome atomic footer marker'), 'The Footer template must render exactly once.');

            // The chrome documents must be what Elementor rendered them as.
            self::assertStringContainsString('data-elementor-type="elemacy_header"', $html);
            self::assertStringContainsString('data-elementor-type="elemacy_footer"', $html);

            // Classic pipeline: Elementor's own per-post CSS for each chrome document.
            foreach ([$header, $footer] as $id) {
                self::assertMatchesRegularExpression(
                    '#(post-' . $id . '\.css|id=[\'"]elementor-post-' . $id . '-(inline-)?css)#',
                    $html,
                    'The classic CSS of chrome document ' . $id . ' must be delivered on the host page.'
                );
            }

            // The atomic markup really is atomic: the element carries its own
            // generated style class, which only the atomic pipeline produces.
            self::assertStringContainsString('e-ecd1001a', $html, 'The header must render as an atomic element.');
            self::assertStringContainsString('e-ecd2001a', $html, 'The footer must render as an atomic element.');
        } finally {
            $harness->restore();
            wp_delete_post((int) $post_id, true);

            if (false === $previous_cache_ttl) {
                delete_option('elementor_element_cache_ttl');
            } else {
                update_option('elementor_element_cache_ttl', $previous_cache_ttl);
            }
        }

        // Atomic CSS is asserted at the pipeline rather than in the rendered
        // <head>. Elementor's Frontend::enqueue_styles() only fires
        // `after_enqueue_post_styles` — the hook its atomic styles manager
        // writes stylesheets from — once per process, and this long-lived
        // PHPUnit process has already rendered other pages by the time this
        // scenario runs. On a real request both halves land in the head
        // together; what this asserts is Elemacy's own half of the contract:
        // that a chrome document yields real atomic CSS, and that its id was
        // handed to Elementor's atomic pipeline.
        $atomic = new \Elemacy\Elementor\Rendering\AtomicStylesRenderer();

        foreach ([$header => 'e-ecd1001a', $footer => 'e-ecd2001a'] as $id => $class) {
            $css = $atomic->render_base((int) $id);

            self::assertNotSame('', $css, 'Chrome document ' . $id . ' must produce atomic CSS.');
            self::assertStringContainsString($class, $css, 'That atomic CSS must scope the element\'s own generated class.');
        }
    }

    public function testAChromeDocumentIsHandedToElementorsOwnAtomicCssPipeline(): void
    {
        $header = $this->documents->create(
            'Chrome registered for atomic css',
            'header',
            'publish',
            self::atomic_block('ecd3001', 'e-ecd3001a', '#123456', 'Chrome registrar marker')
        );

        $seen = [];
        $observe = static function ($post_id) use (&$seen) {
            $seen[] = (int) $post_id;
        };

        // The documented hook Elementor's own Atomic_Styles_Manager collects
        // post ids from; registering a foreign document must reach it.
        add_action('elementor/post/render', $observe);

        try {
            \Elemacy\Core\Container::instance()->make(\Elemacy\Elementor\Rendering\TemplateAssetsRegistrar::class)->register((int) $header);
        } finally {
            remove_action('elementor/post/render', $observe);
        }

        self::assertContains(
            (int) $header,
            $seen,
            "A chrome document's id must be announced on elementor/post/render so Elementor generates its atomic CSS."
        );
    }
}
