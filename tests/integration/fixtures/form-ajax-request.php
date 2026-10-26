<?php

/**
 * One real admin-ajax form submission in its own process. Input (JSON on
 * STDIN): post (the request body), ip, authenticated (sign in the test admin),
 * nonce (true: a valid form nonce for that user; a string: sent as is),
 * forwarded (an X-Forwarded-For value a site filter trusts). Mail and the
 * submitted user are appended to evidence files next to the environment.
 */
define('DOING_AJAX', true);
require dirname(__DIR__) . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
wp_set_current_user(!empty($input['authenticated']) ? get_user_by('login', 'elemacy_test_admin')->ID : 0);
$_GET = [];
$_SERVER['REMOTE_ADDR'] = $input['ip'];
$post = $input['post'] + ['action' => 'elemacy_form_submit'];
if (true === ($input['nonce'] ?? null)) {
    $post['_wpnonce'] = wp_create_nonce('elemacy_form_submit');
} elseif (is_string($input['nonce'] ?? null)) {
    $post['_wpnonce'] = $input['nonce'];
}
$_POST = wp_slash($post);
if (isset($input['forwarded'])) {
    $_SERVER['HTTP_X_FORWARDED_FOR'] = $input['forwarded'];
    add_filter('elemacy/filter/forms/client_ip', static function () {
        return sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']));
    });
}
$directory = dirname($elemacy_test_environment['root']);
add_filter('pre_wp_mail', static function ($result, $mail) use ($directory) {
    file_put_contents($directory . '/form-native-mail.jsonl', wp_json_encode($mail) . "\n", FILE_APPEND | LOCK_EX);
    return true;
}, 999, 2);
add_action('elemacy/action/forms/submitted', static function ($submission) use ($directory) {
    file_put_contents($directory . '/form-native-users.jsonl', wp_json_encode(['user' => $submission->meta()->user_id()]) . "\n", FILE_APPEND | LOCK_EX);
});
do_action((is_user_logged_in() ? 'wp_ajax_' : 'wp_ajax_nopriv_') . 'elemacy_form_submit');
