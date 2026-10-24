<?php

/** Native public AJAX termination cannot be emulated by throwing through its error boundary. */
define('DOING_AJAX', true);
require dirname(__DIR__) . '/bootstrap.php';
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
wp_set_current_user(0);
$_GET = [];
$_POST = wp_slash([
    'action' => 'elemacy_widgets_loop_grid_pagination',
    '_wpnonce' => wp_create_nonce('elemacy_ajax_nonce'),
    'request' => $input['request'],
    'paginate_base' => $input['paginate_base'] ?? '',
]);
do_action('wp_ajax_nopriv_elemacy_widgets_loop_grid_pagination');
