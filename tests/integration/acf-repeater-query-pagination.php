<?php

namespace Elemacy\Tests\Integration;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Integrations\Acf\AcfRowScope;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeater\AcfRepeaterPolicy;
use ElemacyPro\Extensions\LoopDataSources\AcfRepeater\AcfRepeaterQuery;

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Task 8.8: AcfRepeaterQuery::get_items() now only constructs a
 * AcfRepeaterRowLoopItem for a row actually inside the requested page,
 * rather than materializing one for every row and discarding most of them
 * via array_slice(). This locks in that the returned page's content, order,
 * total count and max_num_pages are byte-for-byte the same as the previous
 * "materialize everything, slice at the end" approach — using a small,
 * precisely-labeled fixture so every row's exact position is directly
 * assertable, independent of the large-fixture memory/time measurement in
 * docs/RELEASE-VALIDATION.md.
 */
final class AcfRepeaterQueryPaginationTest extends IntegrationTestCase
{
    private string $field_group = '';
    private string $field_key = '';
    private string $field_name = '';
    private int $post_id = 0;
    private const ROW_COUNT = 6;

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('acf_is_field_type') || !acf_is_field_type('repeater')) {
            self::markTestSkipped('Requires an available ACF Pro installation; provision with --acf-plugin.');
        }

        $suffix = bin2hex(random_bytes(6));
        $this->field_group = 'group_elemacy_pagination_' . $suffix;
        $this->field_key = 'field_elemacy_pagination_rows_' . $suffix;
        $this->field_name = 'elemacy_pagination_rows_' . $suffix;

        $id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ACF pagination fixture'], true);
        self::assertNotInstanceOf(\WP_Error::class, $id);
        $this->post_id = (int) $id;

        acf_add_local_field_group([
            'key' => $this->field_group, 'title' => 'Elemacy pagination rows',
            'fields' => [[
                'key' => $this->field_key, 'name' => $this->field_name, 'label' => 'Rows', 'type' => 'repeater',
                'sub_fields' => [['key' => 'field_elemacy_pagination_label_' . $suffix, 'name' => 'label', 'label' => 'Label', 'type' => 'text']],
            ]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);

        $rows = [];
        for ($i = 0; $i < self::ROW_COUNT; $i++) {
            $rows[] = ['label' => 'Row ' . $i];
        }
        self::assertTrue((bool) update_field($this->field_key, $rows, $this->post_id), 'Native ACF repeater data could not be saved.');
    }

    protected function tearDown(): void
    {
        try {
            if ($this->field_group) {
                acf_remove_local_field_group($this->field_group);
            }
            if ($this->post_id) {
                wp_delete_post($this->post_id, true);
            }
        } finally {
            parent::tearDown();
        }
    }

    public function testOffsetAndPagedRowsResolveTheExactSameLabelsAndOrderAsSlicingTheFullSet(): void
    {
        $query = new AcfRepeaterQuery();

        // rows_offset=1 skips "Row 0"; page 2 of size 2 then skips "Row 1"
        // and "Row 2" too — expecting exactly "Row 3", "Row 4" in order,
        // matching array_slice(range(0..5) minus the first, offset 2, length 2).
        $result = $query->get_items([
            'repeater_field' => $this->field_key, 'source_type' => 'specific_post', 'specific_post_id' => $this->post_id,
            'rows_number' => 2, 'rows_offset' => 1, 'pagination_type' => 'numbers', 'paged' => 2,
        ]);

        self::assertSame(['Row 3', 'Row 4'], self::labels($result->items));
        self::assertSame(self::ROW_COUNT, $result->total_items, 'Total row count must reflect every row, not just the requested page.');
        // (6 rows - 1 offset) / 2 per page = 2.5 -> 3 pages.
        self::assertSame(3, $result->max_num_pages);
    }

    public function testEachPageInSequenceCoversEveryRowExactlyOnceInOrder(): void
    {
        $query = new AcfRepeaterQuery();
        $settings = [
            'repeater_field' => $this->field_key, 'source_type' => 'specific_post', 'specific_post_id' => $this->post_id,
            'rows_number' => 2, 'rows_offset' => 0, 'pagination_type' => 'numbers',
        ];

        $all_labels = [];
        for ($page = 1; $page <= 3; $page++) {
            $result = $query->get_items($settings + ['paged' => $page]);
            self::assertCount(2, $result->items, "Page $page must have exactly 2 rows.");
            $all_labels = array_merge($all_labels, self::labels($result->items));
        }

        self::assertSame(['Row 0', 'Row 1', 'Row 2', 'Row 3', 'Row 4', 'Row 5'], $all_labels, 'Walking every page in order must cover every row exactly once, in the original order.');
    }

    public function testUnboundedRequestFromAnOffsetReturnsEveryRemainingRowInOrder(): void
    {
        $query = new AcfRepeaterQuery();

        $result = $query->get_items([
            'repeater_field' => $this->field_key, 'source_type' => 'specific_post', 'specific_post_id' => $this->post_id,
            'rows_number' => 0, 'rows_offset' => 4,
        ]);

        self::assertSame(['Row 4', 'Row 5'], self::labels($result->items));
        self::assertSame(self::ROW_COUNT, $result->total_items);
    }

    public function testAnOffsetBeyondEveryRowReturnsAnEmptyPageWithTheCorrectTotal(): void
    {
        $query = new AcfRepeaterQuery();

        $result = $query->get_items([
            'repeater_field' => $this->field_key, 'source_type' => 'specific_post', 'specific_post_id' => $this->post_id,
            'rows_number' => 2, 'rows_offset' => 999,
        ]);

        self::assertSame([], $result->items);
        self::assertSame(self::ROW_COUNT, $result->total_items);
    }

    public function testEachRowItemStillResolvesItsOwnCorrectSubFieldValueAfterTheOptimizedWalk(): void
    {
        $query = new AcfRepeaterQuery();

        $result = $query->get_items([
            'repeater_field' => $this->field_key, 'source_type' => 'specific_post', 'specific_post_id' => $this->post_id,
            'rows_number' => 2, 'rows_offset' => 3,
        ]);

        self::assertSame(['Row 3', 'Row 4'], self::labels($result->items));
    }

    /**
     * A repeater inside another repeater's rows is not a loop source: the
     * editor (which walks it inside an active parent row) and visitors (whose
     * policy cannot find it by name) must both get nothing, while the parent
     * repeater itself still loops.
     */
    public function testARepeaterInsideAnotherRepeatersRowYieldsNoItemsInTheEditorOrForVisitors(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $group = 'group_elemacy_nested_' . $suffix;
        $parent_key = 'field_elemacy_parent_' . $suffix;
        $parent = 'elemacy_parent_' . $suffix;
        $child = 'elemacy_child_' . $suffix;
        acf_add_local_field_group([
            'key' => $group, 'title' => 'Elemacy nested rows',
            'fields' => [[
                'key' => $parent_key, 'name' => $parent, 'label' => 'Parent', 'type' => 'repeater',
                'sub_fields' => [[
                    'key' => 'field_elemacy_child_' . $suffix, 'name' => $child, 'label' => 'Child', 'type' => 'repeater',
                    'sub_fields' => [['key' => 'field_elemacy_child_label_' . $suffix, 'name' => 'label', 'label' => 'Label', 'type' => 'text']],
                ]],
            ]],
            'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
        ]);

        try {
            self::assertTrue((bool) update_field($parent_key, [[$child => [['label' => 'A'], ['label' => 'B']]]], $this->post_id));
            $query = new AcfRepeaterQuery();
            $settings = ['repeater_field' => $child, 'source_type' => 'specific_post', 'specific_post_id' => $this->post_id, 'rows_number' => 0, 'rows_offset' => 0];

            $cursor = AcfRowScope::snapshot();
            try {
                self::assertTrue(have_rows($parent, $this->post_id));
                the_row();
                $inside_parent_row = $query->get_items($settings);
            } finally {
                AcfRowScope::restore($cursor);
            }

            self::assertSame([], $inside_parent_row->items, 'The editor must not list a sub-repeater\'s rows.');
            self::assertCount(1, $query->get_items(['repeater_field' => $parent] + $settings)->items, 'The parent repeater still loops.');

            $this->expectException(ValidationException::class);
            (new AcfRepeaterPolicy())->get_public_settings($settings, [], 1);
        } finally {
            acf_remove_local_field_group($group);
        }
    }

    /**
     * @param \Elemacy\Loop\Contracts\LoopItemInterface[] $items
     * @return string[]
     */
    private static function labels(array $items): array
    {
        $labels = [];

        foreach ($items as $item) {
            $item->enter();
            try {
                $labels[] = $item->get_field('label');
            } finally {
                $item->exit();
            }
        }

        return $labels;
    }
}
