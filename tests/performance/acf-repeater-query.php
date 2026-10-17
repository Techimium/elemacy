<?php

/**
 * Task 8.8 performance evidence: time/peak memory for
 * ElemacyPro\Extensions\LoopDataSources\Query\AcfRepeaterQuery::get_items()
 * against a large repeater field, requesting one small page — distinguishing
 * ACF's own unavoidable whole-field load from this plugin's own (now
 * removed) avoidable per-row object materialization.
 *
 * Not a PHPUnit test: memory_get_peak_usage() is monotonic within one PHP
 * process, so a fair before/after comparison needs each mode run in its own
 * process. Run each mode separately and compare the printed numbers:
 *
 *   ELEMACY_TEST_ENV=<env.json> php tests/performance/acf-repeater-query.php --mode=field-only --rows=5000
 *   ELEMACY_TEST_ENV=<env.json> php tests/performance/acf-repeater-query.php --mode=old       --rows=5000 --page-size=10
 *   ELEMACY_TEST_ENV=<env.json> php tests/performance/acf-repeater-query.php --mode=new       --rows=5000 --page-size=10
 *
 * --mode=field-only calls only get_field() — the floor no query
 * implementation can go below, since ACF itself has no bounded/paginated
 * way to read part of a repeater's value.
 * --mode=old re-implements the pre-task-8.8 algorithm inline (materialize
 * every row as a LoopItem, then array_slice() the page out) for a fair,
 * same-process-shape comparison against the real, current get_items().
 * --mode=new calls the real, current AcfRepeaterQuery::get_items().
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require_once __DIR__ . '/../integration/bootstrap.php';

use ElemacyPro\Extensions\LoopDataSources\AcfRepeaterRowLoopItem;
use ElemacyPro\Extensions\LoopDataSources\Query\AcfRepeaterQuery;

function elemacy_perf_arg(string $name, string $default): string
{
    foreach ($GLOBALS['argv'] as $arg) {
        if (0 === strpos($arg, "--{$name}=")) {
            return substr($arg, strlen("--{$name}="));
        }
    }

    return $default;
}

$mode = elemacy_perf_arg('mode', 'new');
$row_count = (int) elemacy_perf_arg('rows', '5000');
$page_size = (int) elemacy_perf_arg('page-size', '10');

if (!function_exists('acf_is_field_type') || !acf_is_field_type('repeater')) {
    fwrite(STDERR, "Requires an available ACF Pro installation; provision with --acf-plugin.\n");
    exit(1);
}

if (!class_exists(AcfRepeaterQuery::class)) {
    fwrite(STDERR, "Requires the Pro plugin active in this environment.\n");
    exit(1);
}

$suffix = bin2hex(random_bytes(6));
$field_group = 'group_elemacy_perf_' . $suffix;
$field_key = 'field_elemacy_perf_rows_' . $suffix;
$field_name = 'elemacy_perf_rows_' . $suffix;

$post_id = wp_insert_post(['post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'ACF repeater perf fixture'], true);
if (is_wp_error($post_id)) {
    fwrite(STDERR, 'Could not create fixture post: ' . $post_id->get_error_message() . "\n");
    exit(1);
}
$post_id = (int) $post_id;

acf_add_local_field_group([
    'key' => $field_group, 'title' => 'Elemacy perf rows',
    'fields' => [[
        'key' => $field_key, 'name' => $field_name, 'label' => 'Rows', 'type' => 'repeater',
        'sub_fields' => [['key' => 'field_elemacy_perf_label_' . $suffix, 'name' => 'label', 'label' => 'Label', 'type' => 'text']],
    ]],
    'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'post']]],
]);

$rows = [];
for ($i = 0; $i < $row_count; $i++) {
    $rows[] = ['label' => 'Row ' . $i];
}
if (!update_field($field_key, $rows, $post_id)) {
    fwrite(STDERR, "Could not save fixture repeater data.\n");
    exit(1);
}
unset($rows);

$cleanup = static function () use ($field_group, $post_id) {
    acf_remove_local_field_group($field_group);
    wp_delete_post($post_id, true);
};

try {
    $settings = [
        'repeater_field' => $field_key, 'source_type' => 'specific_post', 'specific_post_id' => $post_id,
        'rows_number' => $page_size, 'rows_offset' => 0,
    ];

    $start_time = microtime(true);

    if ('field-only' === $mode) {
        $value = get_field($field_key, $post_id);
        $result_count = is_array($value) ? count($value) : 0;
    } elseif ('old' === $mode) {
        $all_items = [];
        $row_index = 0;
        if (have_rows($field_key, $post_id)) {
            while (have_rows($field_key, $post_id)) {
                the_row();
                $all_items[] = new AcfRepeaterRowLoopItem($field_key, $post_id, $row_index);
                ++$row_index;
            }
        }
        $page_items = array_slice($all_items, 0, $page_size);
        $result_count = count($page_items);
    } elseif ('new' === $mode) {
        $result = (new AcfRepeaterQuery())->get_items($settings);
        $result_count = count($result->items);
    } else {
        fwrite(STDERR, "Unknown --mode={$mode}; use field-only, old or new.\n");
        $cleanup();
        exit(1);
    }

    $elapsed_ms = (microtime(true) - $start_time) * 1000;
    // true = OS-allocated blocks (coarse, rounded up to the Zend memory
    // manager's allocation granularity); false = PHP's own precise byte
    // accounting of what's actually live — the one that can reveal a real
    // difference too small to move the coarser, block-rounded figure.
    $peak_bytes_os = memory_get_peak_usage(true);
    $peak_bytes_precise = memory_get_peak_usage(false);

    printf(
        "mode=%s rows=%d page_size=%d result_count=%d elapsed_ms=%.3f peak_os_bytes=%d peak_os_mib=%.2f peak_precise_bytes=%d peak_precise_mib=%.2f\n",
        $mode,
        $row_count,
        $page_size,
        $result_count,
        $elapsed_ms,
        $peak_bytes_os,
        $peak_bytes_os / 1048576,
        $peak_bytes_precise,
        $peak_bytes_precise / 1048576
    );
} finally {
    $cleanup();
}
