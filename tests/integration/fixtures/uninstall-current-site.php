<?php

/**
 * Run with wp --skip-plugins --skip-themes eval-file ... <free|free-retain|pro|pro-retain>.
 * `free` and `pro` uninstall with removal opted in; `free-retain` uninstalls
 * Free without it and checks that everything is kept; `pro-retain` uninstalls
 * Pro without it and checks that the analytics counters and options are kept
 * while the licence and temporary data are gone.
 */
require_once dirname(__DIR__) . '/environment.php';
$environment = elemacy_test_environment((string) getenv('ELEMACY_TEST_ENV'));
if (realpath(ABSPATH) !== $environment['root'] || DB_NAME !== $environment['database']
    || get_option('elemacy_integration_environment') !== $environment['token']) {
    throw new RuntimeException('Refusing uninstall outside the guarded disposable site.');
}
$pro = in_array($args[0] ?? '', ['pro', 'pro-retain'], true);
$retain = in_array($args[0] ?? '', ['free-retain', 'pro-retain'], true);
$prefix = $pro ? 'elemacy_pro' : 'elemacy';
$plugin = $pro ? 'elemacy-pro' : 'elemacy';
$root = dirname(__DIR__, 3);
$source = $pro ? dirname($root) . '/elemacy-pro' : $root;
$check = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$check(!class_exists('Elemacy\Core\Elemacy', false) && !class_exists('ElemacyPro\Core\ElemacyPro', false), 'Uninstall fixture unexpectedly loaded a sibling runtime.');
register_post_type('elemacy_library', ['public' => false, 'supports' => ['title', 'editor', 'revisions']]);
register_taxonomy('uninstall_fixture', ['elemacy_library', 'post'], ['public' => false]);
$owned = [];
$term = wp_insert_term('Uninstall fixture ' . wp_generate_uuid4(), 'uninstall_fixture');
$check(!is_wp_error($term), 'Could not create fixture term.');
$term_id = $term['term_id'];
$unrelated = wp_insert_post(['post_type' => 'post', 'post_title' => 'Uninstall unrelated', 'post_status' => 'publish']);
$owned[] = $unrelated;
wp_set_object_terms($unrelated, $term_id, 'uninstall_fixture');
update_post_meta($unrelated, '_unrelated_fixture', 'keep');
update_post_meta($unrelated, '_elemacy_conditions', ['owned']);
update_post_meta($unrelated, '_elemacy_popup_impressions', '7');
update_post_meta($unrelated, '_elemacy_popup_conversions', '2');
$doc = wp_insert_post(['post_type' => 'elemacy_library', 'post_title' => 'Uninstall document', 'post_content' => 'saved', 'post_status' => 'publish']);
$owned[] = $doc;
wp_set_object_terms($doc, $term_id, 'uninstall_fixture');
$revision = _wp_put_post_revision($doc);
$check($revision && !is_wp_error($revision), 'Could not create native revision.');
$comment = wp_insert_comment(['comment_post_ID' => $doc, 'comment_content' => 'Native comment', 'comment_approved' => 1]);
add_comment_meta($comment, 'fixture', 'owned');
for ($i = 0; $i < 104; ++$i) {
    if (!$pro) {
        $owned[] = wp_insert_post(['post_type' => 'elemacy_library', 'post_title' => 'Cleanup batch', 'post_status' => 'draft']);
    }
    add_option($prefix . '_uninstall_fixture_' . $i, 'owned', '', $i % 2 === 0);
}
add_option('unrelated_uninstall_fixture', 'keep', '', true);
add_option($pro ? 'elemacy_uninstall_sibling' : 'elemacy_pro_uninstall_sibling', 'keep', '', true);
set_transient($prefix . '_uninstall_fixture', 'owned', 60);
if ($pro) {
    // What Pro keeps and what it always removes: the licence, rate-limit windows,
    // counter leases and the licence check's scheduled event.
    update_option('elemacy_pro_license', ['fixture' => 'licence'], true);
    add_option('elemacy_pro_track_limit_fixture', (time() + 60) . ':1', '', false);
    add_option('elemacy_pro_counter_lock_999', 'fixture', '', false);
    wp_schedule_event(time() + 3600, 'daily', 'elemacy_pro_license_check');
}
$css = trailingslashit(wp_upload_dir()['basedir']) . 'elementor/css/post-' . $doc . '.css';
wp_mkdir_p(dirname($css));
file_put_contents($css, '.fixture{}');
$deleted = [];
$capture = static function ($id) use (&$deleted): void { $deleted[] = (int) $id; };
add_action('deleted_post', $capture);
try {
    define('WP_UNINSTALL_PLUGIN', $plugin . '/' . $plugin . '.php');
    if (!$retain) {
        define('ELEMACY_REMOVE_ALL_DATA', true);
    }
    require $source . '/uninstall.php';
    if ($retain && $pro) {
        // Reinstalling Pro by deleting and uploading it keeps every count.
        $check(get_post_meta($unrelated, '_elemacy_popup_impressions', true) === '7', 'Pro uninstall without the opt-in removed the impression counter.');
        $check(get_post_meta($unrelated, '_elemacy_popup_conversions', true) === '2', 'Pro uninstall without the opt-in removed the conversion counter.');
        $check(get_option('elemacy_pro_uninstall_fixture_103') === 'owned', 'Pro uninstall without the opt-in removed its options (the schema version among them).');
        $check(get_option('elemacy_pro_license', false) === false, 'The licence survived Pro uninstall.');
        $check(get_option('elemacy_pro_track_limit_fixture', false) === false, 'A rate-limit record survived Pro uninstall.');
        $check(get_option('elemacy_pro_counter_lock_999', false) === false, 'A counter lease survived Pro uninstall.');
        $check(get_transient('elemacy_pro_uninstall_fixture') === false, 'A transient survived Pro uninstall.');
        $check(false === wp_next_scheduled('elemacy_pro_license_check'), 'The licence check stayed scheduled after Pro uninstall.');
        $check(get_post($doc) && get_post_meta($unrelated, '_elemacy_conditions', true) === ['owned'], 'Pro uninstall touched Free-owned data.');
        for ($i = 0; $i < 104; ++$i) {
            delete_option($prefix . '_uninstall_fixture_' . $i);
        }
        echo wp_json_encode(['plugin' => $plugin, 'status' => 'passed', 'retained' => true]) . "\n";

        return;
    }
    if ($retain) {
        $check(get_post($doc) && get_post($revision) && get_comment($comment), 'Uninstall without the opt-in removed a library document.');
        $check(get_post_meta($unrelated, '_elemacy_conditions', true) === ['owned'], 'Uninstall without the opt-in removed metadata.');
        $check(get_option($prefix . '_uninstall_fixture_103') === 'owned' && get_transient($prefix . '_uninstall_fixture') === 'owned', 'Uninstall without the opt-in removed options.');
        $check(file_exists($css) && $deleted === [], 'Uninstall without the opt-in deleted something.');
        for ($i = 0; $i < 104; ++$i) {
            delete_option($prefix . '_uninstall_fixture_' . $i);
        }
        delete_transient($prefix . '_uninstall_fixture');
        echo wp_json_encode(['plugin' => $plugin, 'status' => 'passed', 'retained' => true]) . "\n";

        return;
    }
    $check(get_post($unrelated) && get_post_meta($unrelated, '_unrelated_fixture', true) === 'keep', 'Unrelated document/meta was removed.');
    $check(get_option('unrelated_uninstall_fixture') === 'keep', 'Unrelated option was removed.');
    $check(get_option($pro ? 'elemacy_uninstall_sibling' : 'elemacy_pro_uninstall_sibling') === 'keep', 'Sibling option was removed.');
    $check(get_option($prefix . '_uninstall_fixture_103', false) === false, 'Cleanup stopped before the last option batch.');
    $check(get_transient($prefix . '_uninstall_fixture') === false, 'Owned transient survived.');
    if ($pro) {
        $check(get_option('elemacy_pro_license', false) === false && get_option('elemacy_pro_track_limit_fixture', false) === false, 'Pro licence or temporary data survived opted-in removal.');
        $check(false === wp_next_scheduled('elemacy_pro_license_check'), 'The licence check stayed scheduled after Pro uninstall.');
        $check((bool) get_post($doc) && (bool) get_post($revision) && (bool) get_comment($comment), 'Pro deleted Free-owned documents.');
        $check(get_post_meta($unrelated, '_elemacy_popup_impressions', true) === '', 'Pro counter survived.');
        $check(get_post_meta($unrelated, '_elemacy_conditions', true) === ['owned'], 'Pro deleted Free metadata.');
    } else {
        $check(!get_post($doc) && !get_post($revision) && !get_comment($comment), 'Native related objects survived.');
        $check(get_comment_meta($comment, 'fixture', true) === '', 'Comment metadata survived.');
        $check(in_array($doc, $deleted, true) && in_array((int) $revision, $deleted, true), 'Native deletion hooks did not run.');
        $check(!file_exists($css), 'Generated document CSS survived.');
        $check((int) get_term($term_id, 'uninstall_fixture')->count === 1, 'Native term count/cache was not updated.');
        $check(get_post_meta($unrelated, '_elemacy_conditions', true) === '', 'Free-owned orphan metadata survived.');
        $check(get_post_meta($unrelated, '_elemacy_popup_impressions', true) === '7', 'Free deleted Pro metadata on unrelated content.');
        global $wpdb;
        $check((int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'elemacy_library'") === 0, 'Library deletion stopped before the final batch.');
    }
    echo wp_json_encode(['plugin' => $plugin, 'status' => 'passed', 'sibling_runtime' => 'absent', 'native_lifecycle' => true]) . "\n";
} finally {
    remove_action('deleted_post', $capture);
    foreach (array_reverse($owned) as $id) {
        wp_delete_post($id, true);
    }
    wp_delete_term($term_id, 'uninstall_fixture');
    delete_option('unrelated_uninstall_fixture');
    delete_option($pro ? 'elemacy_uninstall_sibling' : 'elemacy_pro_uninstall_sibling');
    if ($pro) {
        delete_option('elemacy_pro_license');
        delete_option('elemacy_pro_track_limit_fixture');
        delete_option('elemacy_pro_counter_lock_999');
        wp_clear_scheduled_hook('elemacy_pro_license_check');
    }
    if (file_exists($css)) {
        wp_delete_file($css);
    }
    // The guard itself deliberately uses the plugin-owned prefix; restore only this test marker.
    update_option('elemacy_integration_environment', $environment['token'], false);
}
