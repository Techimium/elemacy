<?php

define('DOING_AJAX', true);
require dirname(__DIR__) . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
wp_set_current_user(!empty($input['authenticated']) ? get_user_by('login', 'elemacy_test_admin')->ID : 0);
$_GET = [];
$_SERVER['REMOTE_ADDR'] = $input['ip'];
$_POST = wp_slash($input['post'] + ['action' => 'elemacy_widgets_form_submit', '_wpnonce' => wp_create_nonce('elemacy_widgets_form_submit')]);
$evidence = dirname($elemacy_test_environment['root']) . '/form-native-mail.jsonl';
add_filter('pre_wp_mail', static function ($result, $mail) use ($evidence) {
    file_put_contents($evidence, wp_json_encode($mail) . "\n", FILE_APPEND | LOCK_EX);
    return true;
}, 999, 2);
do_action((is_user_logged_in() ? 'wp_ajax_' : 'wp_ajax_nopriv_') . 'elemacy_widgets_form_submit');
