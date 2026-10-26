<?php

/**
 * Real network lifecycle fixture; run with WP-CLI --skip-plugins --skip-themes.
 * With the argument `pro-retain` it only uninstalls Pro without
 * ELEMACY_REMOVE_ALL_DATA and checks that every site keeps its analytics
 * counters and options while its temporary data is removed.
 */
$retain_pro = ($args[0] ?? '') === 'pro-retain';
require_once dirname(__DIR__) . '/environment.php';
$environment = elemacy_test_environment((string) getenv('ELEMACY_TEST_ENV'));
if (empty($environment['multisite']) || !is_multisite() || realpath(ABSPATH) !== $environment['root']
    || DB_NAME !== $environment['database'] || get_option('elemacy_integration_environment') !== $environment['token']) {
    throw new RuntimeException('Requires a marked disposable network.');
}
$check = static function ($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$check(!class_exists('Elemacy\Core\Elemacy', false) && !class_exists('ElemacyPro\Core\ElemacyPro', false), 'Sibling runtime was loaded.');
require_once ABSPATH . 'wp-admin/includes/ms.php';
$original_blog = get_current_blog_id();
$original_stack = $GLOBALS['_wp_switched_stack'] ?? [];
$sites = [];
$root = dirname(__DIR__, 3);
$administrator = get_user_by('login', 'elemacy_test_admin')->ID;
$manifest = dirname($environment['root']) . '/uninstall-network-sites.json';
try {
    for ($number = 0; $number < 105; ++$number) {
        $site = wpmu_create_blog('127.0.0.1:' . wp_parse_url($environment['url'], PHP_URL_PORT), '/uninstall-' . $environment['token'] . '-' . $number . '/', 'Cleanup fixture', $administrator, ['public' => 0], get_current_network_id());
        $check(!is_wp_error($site), 'Native site creation failed.');
        $sites[] = (int) $site;
        file_put_contents($manifest, wp_json_encode(['token' => $environment['token'], 'sites' => $sites]));
        switch_to_blog($site);
        try {
            add_option('elemacy_uninstall_network_owned', 'free', '', false);
            add_option('elemacy_pro_uninstall_network_owned', 'pro', '', false);
            add_option('unrelated_uninstall_network', 'keep', '', true);
            add_option('elemacy_pro_track_limit_fixture', (time() + 60) . ':1', '', false);
            $document = wp_insert_post(['post_type' => 'elemacy_library', 'post_status' => 'publish', 'post_title' => 'Network cleanup document']);
            update_post_meta($document, '_elemacy_popup_impressions', '5');
        } finally {
            restore_current_blog();
        }
        if (($number + 1) % 20 === 0) {
            echo 'Created ' . ($number + 1) . " native sites.\n";
        }
    }
    if ($retain_pro) {
        define('WP_UNINSTALL_PLUGIN', 'elemacy-pro/elemacy-pro.php');
        require dirname($root) . '/elemacy-pro/uninstall.php';
        foreach ($sites as $site) {
            $check(get_blog_option($site, 'elemacy_pro_uninstall_network_owned') === 'pro', 'Pro uninstall without the opt-in removed an option on site ' . $site . '.');
            $check(get_blog_option($site, 'elemacy_pro_track_limit_fixture', false) === false, 'A rate-limit record survived Pro uninstall on site ' . $site . '.');
            switch_to_blog($site);
            try {
                global $wpdb;
                $check('5' === (string) $wpdb->get_var("SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elemacy_popup_impressions' LIMIT 1"), 'The impression counter was removed on site ' . $site . '.');
            } finally {
                restore_current_blog();
            }
        }
        echo "elemacy-pro: passed 105-site uninstall without the opt-in (counters and options kept, temporary data removed).\n";

        return;
    }
    $failing_site = $sites[4];
    define('WP_UNINSTALL_PLUGIN', 'elemacy/elemacy.php');
    define('ELEMACY_REMOVE_ALL_DATA', true);
    foreach (['elemacy', 'elemacy-pro'] as $plugin) {
        $prefix = $plugin === 'elemacy' ? 'elemacy' : 'elemacy_pro';
        $key = $prefix . '_uninstall_network_owned';
        $failure = static function ($option) use ($failing_site, $key): void {
            if (get_current_blog_id() === $failing_site && $option === $key) {
                throw new RuntimeException('Injected per-site failure.');
            }
        };
        add_action('delete_option', $failure);
        try {
            require ($plugin === 'elemacy' ? $root : dirname($root) . '/elemacy-pro') . '/uninstall.php';
            throw new LogicException('Injected failure was not reported.');
        } catch (RuntimeException $error) {
            $check(strpos($error->getMessage(), 'cleanup failed on 1 site(s)') !== false, $error->getMessage());
        } finally {
            remove_action('delete_option', $failure);
        }
        $check(get_current_blog_id() === $original_blog && ($GLOBALS['_wp_switched_stack'] ?? []) === $original_stack, 'Failure leaked blog context.');
        $check(get_blog_option(end($sites), $key, false) === false, 'Cleanup stopped before the final site.');
        $check(get_blog_option($failing_site, $key) !== false, 'Failure fixture unexpectedly disappeared.');
        call_user_func($prefix . '_uninstall_all_sites');
        foreach ($sites as $site) {
            $check(get_blog_option($site, $key, false) === false, 'Owned site option survived retry.');
            $check(get_blog_option($site, 'unrelated_uninstall_network') === 'keep', 'Unrelated site data was removed.');
        }
        $check(get_current_blog_id() === $original_blog && ($GLOBALS['_wp_switched_stack'] ?? []) === $original_stack, 'Successful cleanup leaked blog context.');
        echo $plugin . ": passed 105-site cleanup, injected failure, continuation, retry and context restoration.\n";
    }
    echo "No plugin runtime or scheduled cleanup callback was used.\n";
} finally {
    while (get_current_blog_id() !== $original_blog && ms_is_switched()) {
        restore_current_blog();
    }
    foreach (array_reverse($sites) as $site) {
        wpmu_delete_blog($site, true);
    }
    if (file_exists($manifest)) {
        unlink($manifest);
    }
    update_option('elemacy_integration_environment', $environment['token'], false);
}
