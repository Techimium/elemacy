<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Integrations\Acf\AcfCursor;
use Elemacy\Integrations\Acf\AcfFieldReader;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\LoopQuery;
use Elemacy\Loop\Preview\LoopItemPreviewContextProvider;
use Elemacy\Modules\Widgets\Documents\LoopDocument;
use Elemacy\Tests\Integration\Fixtures\NativeAjaxRequest;
use Elementor\Plugin;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeater\AcfRepeaterQuery;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeater\AcfRepeaterRowLoopItem;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeater\AcfRows;

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/fixtures/NativeAjaxRequest.php';

/**
 * Nested ACF repeater loops (sections > items > tags) on a real page, for
 * visitors, through the real public pagination endpoint in a fresh process,
 * and in the editor. The field group is stored (acf_import_field_group), so
 * the later-page process sees it too.
 *
 * @group requires-acf-pro
 */
final class NestedLoopsTest extends IntegrationTestCase
{
    private string $suffix = '';
    private int $field_group_id = 0;
    private int $post = 0;
    private int $host = 0;
    /** @var array<string, int> */
    private array $templates = [];
    /** @var array<string, string> */
    private array $keys = [];
    /** @var mixed */
    private $previous_cache_ttl;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('acf_is_field_type') || !acf_is_field_type('repeater') || !function_exists('acf_import_field_group')) {
            self::markTestSkipped('Requires an available ACF Pro installation; provision with --acf-plugin.');
        }

        // Element caching would emit dynamic widgets as placeholders.
        $this->previous_cache_ttl = get_option('elementor_element_cache_ttl');
        update_option('elementor_element_cache_ttl', 'disable');

        $this->suffix = bin2hex(random_bytes(5));
        $s = $this->suffix;
        $this->keys = [
            'sections' => "field_nl_sections_$s", 'title' => "field_nl_title_$s",
            'items' => "field_nl_items_$s", 'name' => "field_nl_name_$s",
            'tags' => "field_nl_tags_$s", 'tag' => "field_nl_tag_$s",
            'post_title' => "field_nl_post_title_$s",
        ];
        $group = acf_import_field_group([
            'key' => "group_nl_$s", 'title' => 'Nested loops ' . $s,
            'fields' => [
                ['key' => $this->keys['sections'], 'name' => "sections_$s", 'label' => 'Sections', 'type' => 'repeater', 'sub_fields' => [
                    ['key' => $this->keys['title'], 'name' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => $this->keys['items'], 'name' => 'items', 'label' => 'Items', 'type' => 'repeater', 'sub_fields' => [
                        ['key' => $this->keys['name'], 'name' => 'name', 'label' => 'Name', 'type' => 'text'],
                        ['key' => $this->keys['tags'], 'name' => 'tags', 'label' => 'Tags', 'type' => 'repeater', 'sub_fields' => [
                            ['key' => $this->keys['tag'], 'name' => 'tag', 'label' => 'Tag', 'type' => 'text'],
                        ]],
                    ]],
                ]],
                ['key' => $this->keys['post_title'], 'name' => 'title', 'label' => 'Post title', 'type' => 'text'],
            ],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);
        $this->field_group_id = (int) ($group['ID'] ?? 0);
        self::assertGreaterThan(0, $this->field_group_id, 'The nested field group could not be stored.');

        $this->post = (int) wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Nested loops source ' . $s]);
        $this->save_sections(self::sections());
    }

    protected function tearDown(): void
    {
        try {
            while (null !== LoopContext::current_frame()) {
                LoopContext::pop();
            }
            AcfCursor::restore([]);
            if ($this->host) {
                wp_delete_post($this->host, true);
            }
            if ($this->post) {
                wp_delete_post($this->post, true);
            }
            if ($this->field_group_id) {
                acf_delete_field_group($this->field_group_id);
            }
            if (null !== $this->previous_cache_ttl) {
                false === $this->previous_cache_ttl ? delete_option('elementor_element_cache_ttl') : update_option('elementor_element_cache_ttl', $this->previous_cache_ttl);
            }
            AcfRows::reset();
        } finally {
            parent::tearDown();
        }
    }

    /* ── Rendering for visitors ─────────────────────────────────────────── */

    public function testEachLevelRendersOnlyItsOwnParentsRowsAndReadsItsAncestors(): void
    {
        $html = $this->render_host();

        self::assertOrder($html, ['[S=Fruits]', '[I=Apple]', '[IS=Fruits]', '[T=red]', '[TI=Apple]', '[TS=Fruits]', '[S=Vegetables]', '[I=Carrot]', '[IS=Vegetables]', '[T=orange]', '[TI=Carrot]', '[TS=Vegetables]']);
        foreach (['[I=Banana]', '[T=sweet]', '[T=yellow]', '[TX='] as $not_on_page_one) {
            self::assertStringNotContainsString($not_on_page_one, $html, "$not_on_page_one is not on page one (or has no level that far up).");
        }
    }

    public function testOnlyLoopsWithANextPageCarryASignedContext(): void
    {
        $payloads = self::payloads($this->render_host());

        self::assertCount(4, $payloads, 'Two items loops and two tags loops paginate.');
        $signed = array_filter($payloads, static fn(array $p): bool => '' !== $p['context']);
        self::assertCount(2, $signed, 'Only Fruits\' items loop and Apple\'s tags loop have a next page.');
        foreach ($signed as $payload) {
            $claims = self::claims($payload['context']);
            self::assertSame('nested_loop', $claims['type']);
            self::assertSame(count($claims['facts']['lineage']), count(explode('/', $claims['facts']['parent'])));
        }
    }

    public function testLoadMoreAtDepthThreeStaysUnderItsParentAndGrandparent(): void
    {
        $tags = $this->payload_for($this->render_host(), 'nltags');
        $response = $this->page($tags, 2);

        self::assertTrue($response['success'] ?? false, wp_json_encode($response));
        self::assertOrder($response['data']['items'], ['[T=sweet]', '[TI=Apple]', '[TS=Fruits]']);
        self::assertStringNotContainsString('[T=red]', $response['data']['items']);
        self::assertSame(3, $response['data']['next_page']);
    }

    public function testAnAppendedItemPaginatesItsOwnInnerLoopUnderTheFullChain(): void
    {
        $items = $this->payload_for($this->render_host(), 'nlitems');
        $page_two = $this->page($items, 2);

        self::assertTrue($page_two['success'] ?? false, wp_json_encode($page_two));
        self::assertOrder($page_two['data']['items'], ['[I=Banana]', '[IS=Fruits]', '[T=yellow]', '[TI=Banana]', '[TS=Fruits]']);

        $banana_tags = $this->payload_for($page_two['data']['items'], 'nltags');
        $claims = self::claims($banana_tags['context']);
        self::assertCount(2, $claims['facts']['lineage'], 'The token minted on page two continues the signed lineage.');
        self::assertCount(2, explode('/', $claims['facts']['parent']));

        $tags = $this->page($banana_tags, 2);
        self::assertTrue($tags['success'] ?? false, wp_json_encode($tags));
        self::assertOrder($tags['data']['items'], ['[T=long]', '[TI=Banana]', '[TS=Fruits]']);
    }

    public function testLoadMoreIsRefusedOnceTheParentRowsWereReorderedOrEdited(): void
    {
        $tags = $this->payload_for($this->render_host(), 'nltags');

        $sections = self::sections();
        $sections[0]['items'] = array_reverse($sections[0]['items']);
        $this->save_sections($sections);
        $reordered = $this->page($tags, 2);
        self::assertFalse($reordered['success'] ?? true, 'A reordered parent must not serve another row\'s children: ' . wp_json_encode($reordered));

        $sections = self::sections();
        $sections[0]['items'][0]['name'] = 'Green apple';
        $this->save_sections($sections);
        self::assertFalse($this->page($tags, 2)['success'] ?? true, 'An edited parent row is refused too.');
    }

    public function testAForeignOrForgedChainAndAnUnpublishedRootAreRefused(): void
    {
        $html = $this->render_host();
        $tags = $this->payload_for($html, 'nltags');
        $items = $this->payload_for($html, 'nlitems');

        self::assertFalse($this->page(['context' => $items['context']] + $tags, 2)['success'] ?? true, 'Another widget\'s chain is refused.');

        [$encoded, $mac] = explode('.', $tags['context'], 2);
        $claims = self::claims($tags['context']);
        $claims['facts']['parent'] = str_replace('.0', '.1', $claims['facts']['parent']);
        $forged = rtrim(strtr(base64_encode((string) wp_json_encode($claims)), '+/', '-_'), '=') . '.' . $mac;
        self::assertFalse($this->page(['context' => $forged] + $tags, 2)['success'] ?? true, 'A forged chain is refused.');

        wp_update_post(['ID' => $this->post, 'post_status' => 'draft']);
        self::assertFalse($this->page($tags, 2)['success'] ?? true, 'An unpublished root is refused.');
    }

    public function testTheSameInnerRowsUnderTwoOuterWidgetsNeverShareAStyleScope(): void
    {
        $html = $this->render_host(2);

        preg_match_all('/class="elemacy-loop-item elemacy-loop-item-([^"\s]+)"/', $html, $matches);
        self::assertGreaterThan(10, count($matches[1]));
        self::assertSame(count($matches[1]), count(array_unique($matches[1])), 'Every rendered occurrence has its own selector.');
    }

    public function testRenderingCostsNoExtraQueriesPerRow(): void
    {
        $count = function (int $rows): int {
            $sections = [];
            for ($s = 0; $s < $rows; $s++) {
                $items = [];
                for ($i = 0; $i < $rows; $i++) {
                    $tags = [];
                    for ($t = 0; $t < $rows; $t++) {
                        $tags[] = ['tag' => "t$s$i$t"];
                    }
                    $items[] = ['name' => "i$s$i", 'tags' => $tags];
                }
                $sections[] = ['title' => "s$s", 'items' => $items];
            }
            $this->save_sections($sections);
            AcfRows::reset();
            $this->render_host(1, 0);
            AcfRows::reset();
            // Elementor 4.3 looks up its interactions cache meta on every
            // frontend render of a document without interactions, so every
            // loop item of any source costs that one query; it is Elementor's
            // own per-template work, not the nested loop's, and is not counted.
            $queries = 0;
            $count_query = static function ($sql) use (&$queries) {
                if (false === strpos((string) $sql, "'elementor-interactions-cache'")) {
                    ++$queries;
                }

                return $sql;
            };
            add_filter('query', $count_query);
            try {
                $this->render_host(1, 0);
            } finally {
                remove_filter('query', $count_query);
            }

            return $queries;
        };

        $small = $count(2);
        $large = $count(4);

        self::assertSame($small, $large, "Rendering 8 innermost rows took $small queries; 64 took $large.");
    }

    /* ── Native row context ─────────────────────────────────────────────── */

    public function testTheCursorMatchesNativeIterationAtDepthThreeWhateverTheRowIndexOffset(): void
    {
        foreach ([1, 0] as $offset) {
            acf_update_setting('row_index_offset', $offset);
            try {
                $native = [];
                if (have_rows($this->keys['sections'], $this->post)) {
                    while (have_rows($this->keys['sections'], $this->post)) {
                        the_row();
                        $section = get_row_index();
                        while (have_rows($this->keys['items'])) {
                            the_row();
                            $item = get_row_index();
                            while (have_rows($this->keys['tags'])) {
                                the_row();
                                $native[] = [$section, $item, get_row_index(), get_sub_field('tag')];
                            }
                        }
                    }
                }

                $replayed = [];
                foreach (self::sections() as $s => $section) {
                    foreach ($section['items'] as $i => $item) {
                        foreach ($item['tags'] as $t => $tag) {
                            $cursor = new AcfCursor();
                            self::assertTrue($cursor->enter($this->post, [[$this->keys['sections'], $s], [$this->keys['items'], $i], [$this->keys['tags'], $t]]));
                            $replayed[] = [$s + $offset, $i + $offset, get_row_index(), get_sub_field('tag')];
                            $cursor->exit();
                        }
                    }
                }

                self::assertNotEmpty($native);
                self::assertSame($native, $replayed, "Row index offset $offset");
                self::assertSame([], AcfCursor::snapshot());
            } finally {
                acf_update_setting('row_index_offset', 1);
            }
        }
    }

    public function testEnteringARowRestoresTheEnclosingCursorAndAMissingRowChangesNothing(): void
    {
        self::assertTrue(have_rows($this->keys['sections'], $this->post));
        the_row();
        $enclosing = AcfCursor::snapshot();

        try {
            $cursor = new AcfCursor();
            self::assertTrue($cursor->enter($this->post, [[$this->keys['sections'], 1], [$this->keys['items'], 0]]));
            self::assertSame('Carrot', get_sub_field('name'));
            self::assertTrue($cursor->enter($this->post, [[$this->keys['sections'], 0], [$this->keys['items'], 1]]), 'An already entered cursor can be entered again.');
            self::assertSame('Banana', get_sub_field('name'));
            $cursor->exit();
            self::assertSame('Carrot', get_sub_field('name'));
            $cursor->exit();
            self::assertSame($enclosing, AcfCursor::snapshot());
            self::assertSame('Fruits', get_sub_field('title'));

            self::assertFalse($cursor->enter($this->post, [[$this->keys['sections'], 1], [$this->keys['items'], 5]]));
            self::assertFalse($cursor->enter($this->post, [[$this->keys['sections'], 0], [$this->keys['tags'], 0]]), 'A key that is not a sub-field of that level is refused.');
            self::assertSame($enclosing, AcfCursor::snapshot());
        } finally {
            AcfCursor::restore([]);
        }
    }

    public function testAPostCardInsideARowReadsItsOwnFieldNotTheRowsSameNamedSubField(): void
    {
        $card = (int) wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'Card ' . $this->suffix]);
        update_field($this->keys['post_title'], 'The card\'s own title', $card);

        try {
            $row = new AcfRepeaterRowLoopItem($this->keys['sections'], $this->post, 0);
            $row->enter();
            try {
                $reader = new AcfFieldReader();
                self::assertSame('Fruits', $reader->get_field_value('title'));

                $post = new PostLoopItem(get_post($card));
                $post->enter();
                try {
                    self::assertSame('The card\'s own title', $reader->get_field_value('title'));
                } finally {
                    $post->exit();
                }

                self::assertSame('Fruits', $reader->get_field_value('title'), 'The row reads again after the post card ends.');
            } finally {
                $row->exit();
            }
        } finally {
            wp_delete_post($card, true);
        }
    }

    /* ── Parent Row resolution ──────────────────────────────────────────── */

    public function testParentRowReadsOnlyARepeaterDefinedDirectlyUnderTheEnclosingRow(): void
    {
        $query = new AcfRepeaterQuery();
        $section = new AcfRepeaterRowLoopItem($this->keys['sections'], $this->post, 0);
        $frame = LoopContext::push($section);
        $section->enter();

        try {
            $items = $query->query(new LoopQuery(['repeater_field' => 'items', 'source_type' => 'parent_row'], $frame));
            self::assertSame(2, $items->total_items);
            self::assertSame([[$this->keys['sections'], 0], [$this->keys['items'], 1]], $items->items[1]->get_path());

            foreach (["sections_{$this->suffix}", 'tags', 'title', 'nope'] as $not_a_sub_repeater) {
                self::assertSame([], $query->query(new LoopQuery(['repeater_field' => $not_a_sub_repeater, 'source_type' => 'parent_row'], $frame))->items, "$not_a_sub_repeater is not a repeater directly under sections.");
            }
        } finally {
            $section->exit();
            LoopContext::pop();
        }

        $outside = $query->query(new LoopQuery(['repeater_field' => 'items', 'source_type' => 'parent_row']));
        self::assertSame([], $outside->items);
        self::assertNotNull($outside->notice, 'Outside a row the editor is told why.');

        $top_level = $query->get_items(['repeater_field' => 'items', 'source_type' => 'specific_post', 'specific_post_id' => $this->post]);
        self::assertSame([], $top_level->items, 'A sub-repeater is still never read as a top-level field.');
    }

    public function testASubRepeaterNamedLikeItsParentDescendsInsteadOfAdvancingTheParent(): void
    {
        $s = $this->suffix;
        $outer_key = "field_nl_same_outer_$s";
        $inner_key = "field_nl_same_inner_$s";
        $group = acf_import_field_group([
            'key' => "group_nl_same_$s", 'title' => 'Same names ' . $s,
            'fields' => [['key' => $outer_key, 'name' => "same_$s", 'label' => 'Outer', 'type' => 'repeater', 'sub_fields' => [
                ['key' => "field_nl_same_label_$s", 'name' => 'label', 'label' => 'Label', 'type' => 'text'],
                ['key' => $inner_key, 'name' => "same_$s", 'label' => 'Inner', 'type' => 'repeater', 'sub_fields' => [
                    ['key' => "field_nl_same_inner_label_$s", 'name' => 'label', 'label' => 'Label', 'type' => 'text'],
                ]],
            ]]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);

        try {
            update_field($outer_key, [
                ['label' => 'Outer A', "same_$s" => [['label' => 'Inner A1'], ['label' => 'Inner A2']]],
                ['label' => 'Outer B', "same_$s" => [['label' => 'Inner B1']]],
            ], $this->post);
            AcfRows::reset();

            $query = new AcfRepeaterQuery();
            $outer = $query->get_items(['repeater_field' => "same_$s", 'source_type' => 'specific_post', 'specific_post_id' => $this->post]);
            $labels = [];
            foreach ($outer->items as $row) {
                $frame = LoopContext::push($row);
                $row->enter();
                try {
                    $labels[] = get_sub_field('label');
                    foreach ($query->query(new LoopQuery(['repeater_field' => "same_$s", 'source_type' => 'parent_row'], $frame))->items as $inner) {
                        $inner->enter();
                        $labels[] = get_sub_field('label');
                        $inner->exit();
                    }
                    $labels[] = get_sub_field('label');
                } finally {
                    $row->exit();
                    LoopContext::pop();
                }
            }

            self::assertSame(['Outer A', 'Inner A1', 'Inner A2', 'Outer A', 'Outer B', 'Inner B1', 'Outer B'], $labels);
        } finally {
            acf_delete_field_group((int) $group['ID']);
        }
    }

    /* ── Editor ─────────────────────────────────────────────────────────── */

    public function testAParentRowLoopEditedInsideItsOuterTemplateShowsThatPreviewRowsItems(): void
    {
        $this->render_host(); // builds the templates
        $section_template = $this->templates['section'];
        $document = Plugin::$instance->documents->get($section_template, false);
        $document->save(['settings' => [
            'preview_data_source' => 'acf_repeater', 'repeater_field' => "sections_{$this->suffix}",
            'source_type' => 'specific_post', 'specific_post_id' => $this->post,
        ]]);

        $editor = Plugin::$instance->editor;
        $was_edit_mode = $editor->is_edit_mode();
        $editor->set_edit_mode(true);
        Plugin::$instance->documents->switch_to_document(Plugin::$instance->documents->get($section_template, false));

        try {
            $widget = Plugin::$instance->elements_manager->create_element_instance(self::grid('nlitems', [
                'data_source' => 'acf_repeater', 'repeater_field' => 'items', 'source_type' => 'parent_row',
                'template_id' => $this->templates['item'], 'rows_number' => 0,
            ]));
            ob_start();
            $widget->render_content();
            $html = ob_get_clean();
        } finally {
            Plugin::$instance->documents->restore_document();
            $editor->set_edit_mode($was_edit_mode);
        }

        self::assertOrder($html, ['[I=Apple]', '[T=red]', '[I=Banana]', '[T=yellow]']);
        self::assertStringNotContainsString('[I=Carrot]', $html, 'Only the preview section\'s items.');
        self::assertSame([], LoopContext::frames());
    }

    public function testANestedPathPreviewEntersARowOfTheLastSubRepeaterUnderItsAncestors(): void
    {
        $id = $this->documents->create('Nested preview ' . $this->suffix, 'loop');
        $document = Plugin::$instance->documents->get($id, false);
        $document->save(['settings' => [
            'preview_data_source' => 'acf_repeater', 'source_type' => 'specific_post', 'specific_post_id' => $this->post,
            'acf_repeater_preview_path' => implode('>', [$this->keys['sections'], $this->keys['items'], $this->keys['tags']]),
        ]]);
        $document = Plugin::$instance->documents->get($id, false);
        self::assertInstanceOf(LoopDocument::class, $document);

        $item = $document->get_preview_item();
        self::assertInstanceOf(AcfRepeaterRowLoopItem::class, $item);

        $provider = new LoopItemPreviewContextProvider();
        $context = $provider->enter($item);
        try {
            self::assertSame('red', get_sub_field('tag'));
            self::assertSame('Apple', LoopContext::at_level(1, static fn() => get_sub_field('name')));
            self::assertSame('Fruits', LoopContext::at_level(2, static fn() => get_sub_field('title')));
        } finally {
            $provider->leave($context);
        }

        self::assertSame([], LoopContext::frames());
        self::assertSame([], AcfCursor::snapshot());
    }

    /* ── Fixture ────────────────────────────────────────────────────────── */

    private static function sections(): array
    {
        return [
            ['title' => 'Fruits', 'items' => [
                ['name' => 'Apple', 'tags' => [['tag' => 'red'], ['tag' => 'sweet'], ['tag' => 'round']]],
                ['name' => 'Banana', 'tags' => [['tag' => 'yellow'], ['tag' => 'long']]],
            ]],
            ['title' => 'Vegetables', 'items' => [
                ['name' => 'Carrot', 'tags' => [['tag' => 'orange']]],
            ]],
        ];
    }

    private function save_sections(array $sections): void
    {
        // update_field() reports false when the row count is unchanged, so check what was stored.
        update_field($this->keys['sections'], $sections, $this->post);
        AcfRows::reset();
        $stored = get_field($this->keys['sections'], $this->post, false);
        self::assertSame(count($sections), is_array($stored) ? count($stored) : -1, 'Nested rows could not be saved.');
    }

    /**
     * Renders the host page as a visitor: $outer Loop Grids over the
     * sections, each item template holding a paginated items loop, whose
     * item template holds a paginated tags loop.
     */
    private function render_host(int $outer = 1, int $per_page = 1): string
    {
        if (!$this->templates) {
            $pagination = ['pagination_type' => 'load_more', 'rows_number' => $per_page];
            $this->templates['tag'] = $this->documents->create('Tag card ' . $this->suffix, 'loop', 'publish', self::container([
                self::heading('nlh1', self::tag('tag', 0, '[T=')),
                self::heading('nlh2', self::tag('name', 1, '[TI=')),
                self::heading('nlh3', self::tag('title', 2, '[TS=')),
                self::heading('nlh4', self::tag('title', 3, '[TX=')),
            ]));
            $this->templates['item'] = $this->documents->create('Item card ' . $this->suffix, 'loop', 'publish', self::container([
                self::heading('nlh5', self::tag('name', 0, '[I=')),
                self::heading('nlh6', self::tag('title', 1, '[IS=')),
                self::grid('nltags', ['data_source' => 'acf_repeater', 'repeater_field' => 'tags', 'source_type' => 'parent_row', 'template_id' => $this->templates['tag']] + $pagination),
            ]));
            $this->templates['section'] = $this->documents->create('Section card ' . $this->suffix, 'loop', 'publish', self::container([
                self::heading('nlh7', self::tag('title', 0, '[S=')),
                self::grid('nlitems', ['data_source' => 'acf_repeater', 'repeater_field' => 'items', 'source_type' => 'parent_row', 'template_id' => $this->templates['item']] + $pagination),
            ]));
        }

        $grids = [];
        for ($i = 0; $i < $outer; $i++) {
            $grids[] = self::grid('nlouter' . $i, [
                'data_source' => 'acf_repeater', 'repeater_field' => "sections_{$this->suffix}", 'source_type' => 'specific_post',
                'specific_post_id' => $this->post, 'template_id' => $this->templates['section'], 'rows_number' => 0,
            ]);
        }

        if ($this->host) {
            wp_delete_post($this->host, true);
        }
        $this->host = (int) wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Nested loops host ' . $this->suffix]);
        update_post_meta($this->host, '_elementor_edit_mode', 'builder');
        update_post_meta($this->host, '_elementor_version', ELEMENTOR_VERSION);
        update_post_meta($this->host, '_elementor_data', wp_slash((string) wp_json_encode(self::container($grids))));

        $user = get_current_user_id();
        wp_set_current_user(0);
        query_posts(['p' => $this->host, 'post_type' => 'page']);
        the_post();
        try {
            return (string) Plugin::$instance->frontend->get_builder_content($this->host, true);
        } finally {
            wp_reset_query();
            wp_set_current_user($user);
        }
    }

    /** @return array<string, mixed> The page-request payload of the first loop widget $widget_id in $html. */
    private function payload_for(string $html, string $widget_id): array
    {
        foreach (self::payloads($html) as $payload) {
            if ($widget_id === $payload['widget_id'] && '' !== $payload['context']) {
                return $payload;
            }
        }

        self::fail("No signed $widget_id loop in: $html");
    }

    /** @return array<int, array<string, mixed>> */
    private static function payloads(string $html): array
    {
        preg_match_all('/data-elemacy-loop-request="([^"]*)"/', $html, $matches);

        return array_map(static fn(string $raw): array => json_decode(html_entity_decode($raw, ENT_QUOTES), true), $matches[1]);
    }

    private function page(array $payload, int $page): array
    {
        $payload['page'] = $page;
        $body = NativeAjaxRequest::loop((string) wp_json_encode($payload), home_url('/page/%#%/'));
        $response = json_decode($body, true);
        self::assertIsArray($response, 'The endpoint must answer JSON: ' . $body);

        return $response;
    }

    private static function claims(string $token): array
    {
        [$encoded] = explode('.', $token, 2);

        return json_decode((string) base64_decode(strtr($encoded, '-_', '+/')), true);
    }

    private static function assertOrder(string $html, array $needles): void
    {
        $at = 0;
        foreach ($needles as $needle) {
            $found = strpos($html, $needle, $at);
            self::assertNotFalse($found, "Expected $needle after offset $at in: " . strip_tags($html));
            $at = $found + strlen($needle);
        }
    }

    private static function tag(string $key, int $level, string $before): string
    {
        $settings = ['key' => $key, 'before' => $before, 'after' => ']'];
        if ($level > 0) {
            $settings['loop_level'] = (string) $level;
        }

        return '[elementor-tag id="' . substr(md5($key . $level . $before), 0, 7) . '" name="elemacy-acf-field" settings="' . rawurlencode((string) wp_json_encode($settings)) . '"]';
    }

    private static function heading(string $id, string $tag): array
    {
        return ['id' => $id, 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['title' => '', 'header_size' => 'h3', '__dynamic__' => ['title' => $tag]], 'elements' => []];
    }

    private static function grid(string $id, array $settings): array
    {
        return ['id' => $id, 'elType' => 'widget', 'widgetType' => 'elemacy-loop-grid', 'settings' => $settings, 'elements' => []];
    }

    private static function container(array $elements): array
    {
        return [['id' => 'nlc' . substr(md5(serialize($elements)), 0, 4), 'elType' => 'container', 'settings' => [], 'elements' => $elements]];
    }
}
