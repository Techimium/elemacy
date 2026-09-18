<?php

/** One actual WordPress AJAX request per process, including native JSON/die handling. */
define('DOING_AJAX', true);
require dirname(__DIR__) . '/bootstrap.php';

class ElemacyFixtureAjaxRouter extends \Elemacy\Core\AjaxRouter
{
    protected static $routes = [];
}

class ElemacyFixtureAjaxController
{
    public static int $constructed = 0;

    public function __construct()
    {
        ++self::$constructed;
    }

    public function index()
    {
        wp_send_json_success(['constructed' => self::$constructed, 'executed' => 1]);
    }
}

class ElemacyFixtureUnrelatedAjaxController
{
    public function __construct()
    {
        throw new Error('secret-constructor-must-be-lazy');
    }

    public function index()
    {
        throw new Error('must-not-run');
    }
}

wp_set_current_user(0);
$case = $argv[1] ?? '';
if ($case === 'omitted') {
    add_filter('doing_it_wrong_trigger_error', '__return_false');
    ElemacyFixtureAjaxRouter::add_action('elemacy_fixture_omitted', [ElemacyFixtureAjaxController::class, 'index']);
    ElemacyFixtureAjaxRouter::register();
    wp_send_json_success(['registered' => (bool) has_action('wp_ajax_nopriv_elemacy_fixture_omitted'), 'constructed' => ElemacyFixtureAjaxController::$constructed]);
}
if ($case === 'admin') {
    ElemacyFixtureAjaxRouter::add_action('elemacy_fixture_admin', [ElemacyFixtureAjaxController::class, 'index'])->require_capability('manage_options');
    ElemacyFixtureAjaxRouter::register();
    // Directly invoke the authenticated hook as a guest as an additional policy defense check.
    do_action('wp_ajax_elemacy_fixture_admin');
}
ElemacyFixtureAjaxRouter::add_action('elemacy_fixture_good', [ElemacyFixtureAjaxController::class, 'index'])->for_public()->with_nonce('fixture_nonce');
ElemacyFixtureAjaxRouter::add_action('elemacy_fixture_broken', [ElemacyFixtureUnrelatedAjaxController::class, 'index'])->for_public();
ElemacyFixtureAjaxRouter::register();
$_POST['_wpnonce'] = $case === 'bad-nonce' ? 'invalid' : wp_create_nonce('fixture_nonce');
do_action('wp_ajax_nopriv_elemacy_fixture_' . ($case === 'broken' ? 'broken' : 'good'));
