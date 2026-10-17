<?php

/**
 * Test bootstrap: a minimal in-memory WordPress stub layer plus the plugin's
 * composer autoloader, so business logic can be executed without a WordPress
 * install. Only the functions the tested classes actually call are stubbed,
 * each faithful to core's documented behavior for the inputs used in tests.
 */

define('ABSPATH', '/tmp/wp/');
define('WPINC', 'wp-includes');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

// Real plugin-root constants, needed only by tests that exercise
// Elemacy::init() itself (task 6.2) — its own require_once of
// Config/modules.php is a real filesystem read, not something a stub
// can stand in for.
define('ELEMACY_PATH', dirname(__DIR__, 2) . '/');
define('ELEMACY_FILE', ELEMACY_PATH . 'elemacy.php');
define('ELEMACY_PLUGIN_BASE', 'elemacy/elemacy.php');
define('ELEMACY_VERSION', '0.0.0-test');

$GLOBALS['__wp_options'] = [];
$GLOBALS['__wp_actions'] = [];
$GLOBALS['__wp_actions_fired'] = [];
$GLOBALS['__current_post_id'] = 0;
$GLOBALS['__current_post_content'] = '';
$GLOBALS['__wp_post_meta'] = [];
$GLOBALS['__password_required_post_ids'] = [];

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['__wp_actions'][$hook][$priority][] = [$callback, $accepted_args];

    return true;
}

function register_activation_hook($file, $callback)
{
    // Never fired in these tests: activation-time behavior (multisite
    // seeding) is out of scope for the fast harness, which never runs
    // WordPress's own activation sequence.
    return true;
}

function is_admin()
{
    return false;
}

/**
 * Task 11.7's own default-active-module seeding is the first thing in this
 * harness that ever leaves ThemeBuilder active by the time later tests run,
 * which makes its LocationRegistry conditions (single/search/archive/404)
 * reachable for the first time. None of the fast-harness tests exercise a
 * specific request type, so these simply report "no", matching is_admin()
 * above.
 */
function is_singular($post_types = '')
{
    return false;
}

function is_search()
{
    return $GLOBALS['__wp_is_search'] ?? false;
}

function is_archive()
{
    return false;
}

function is_home()
{
    return false;
}

function is_404()
{
    return false;
}

/**
 * Minimal multisite stubs (task 11.7): SiteBatchIterator's own pagination/
 * restoration logic is plain PHP against these four WordPress functions, so
 * it is directly testable here even though — per register_activation_hook()'s
 * own comment above — the *activation sequence itself* stays out of the fast
 * harness's scope. $GLOBALS['__wp_multisite_sites'] is a flat, fake ID list
 * a test seeds directly; switch_to_blog()/restore_current_blog() only track
 * the call sequence (a stack), not real per-blog option isolation.
 */
$GLOBALS['__wp_multisite'] = false;
$GLOBALS['__wp_multisite_sites'] = [];
$GLOBALS['__wp_blog_stack'] = [];
$GLOBALS['__wp_current_blog_id'] = 1;

function is_multisite()
{
    return $GLOBALS['__wp_multisite'];
}

function get_sites($args = [])
{
    $offset = (int) ($args['offset'] ?? 0);
    $number = (int) ($args['number'] ?? 0);
    $ids = $GLOBALS['__wp_multisite_sites'];

    if ($number <= 0) {
        return array_slice($ids, $offset);
    }

    return array_slice($ids, $offset, $number);
}

function switch_to_blog($site_id)
{
    $GLOBALS['__wp_blog_stack'][] = $GLOBALS['__wp_current_blog_id'];
    $GLOBALS['__wp_current_blog_id'] = (int) $site_id;

    return true;
}

function get_current_blog_id()
{
    return $GLOBALS['__wp_current_blog_id'];
}

function restore_current_blog()
{
    $GLOBALS['__wp_current_blog_id'] = array_pop($GLOBALS['__wp_blog_stack']) ?? 1;

    return true;
}

/** A separate store from $GLOBALS['__wp_options']: network options are not per-blog. */
function get_site_option($key, $default_value = false)
{
    return array_key_exists($key, $GLOBALS['__wp_site_options'] ?? []) ? $GLOBALS['__wp_site_options'][$key] : $default_value;
}

function update_site_option($key, $value)
{
    $GLOBALS['__wp_site_options'][$key] = $value;

    return true;
}

function delete_site_option($key)
{
    unset($GLOBALS['__wp_site_options'][$key]);

    return true;
}

/**
 * SiteInstaller::seed_new_site() (the wp_initialize_site listener) checks this
 * before seeding a newly-created site, guarding against network-activating
 * plugins that a given site opted out of. Defaults to true (the common
 * case a network-activated plugin's own new-site hook actually runs under)
 * so a test only needs to flip it to exercise the early-return branch.
 */
$GLOBALS['__wp_plugin_active_for_network'] = true;

function is_plugin_active_for_network($plugin)
{
    return $GLOBALS['__wp_plugin_active_for_network'];
}

function did_action($hook)
{
    return $GLOBALS['__wp_actions_fired'][$hook] ?? 0;
}

function do_action($hook, ...$args)
{
    $GLOBALS['__wp_actions_fired'][$hook] = ($GLOBALS['__wp_actions_fired'][$hook] ?? 0) + 1;

    if (empty($GLOBALS['__wp_actions'][$hook])) {
        return;
    }

    $callbacks_by_priority = $GLOBALS['__wp_actions'][$hook];
    ksort($callbacks_by_priority);

    foreach ($callbacks_by_priority as $callbacks) {
        foreach ($callbacks as [$callback, $accepted_args]) {
            $callback(...array_slice($args, 0, $accepted_args));
        }
    }
}

function remove_all_actions($hook, $priority = false)
{
    unset($GLOBALS['__wp_actions'][$hook]);

    return true;
}

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1)
{
    return add_action($hook, $callback, $priority, $accepted_args);
}

function apply_filters($hook, $value, ...$args)
{
    if (empty($GLOBALS['__wp_actions'][$hook])) {
        return $value;
    }

    $callbacks_by_priority = $GLOBALS['__wp_actions'][$hook];
    ksort($callbacks_by_priority);

    foreach ($callbacks_by_priority as $callbacks) {
        foreach ($callbacks as [$callback, $accepted_args]) {
            $call_args = array_slice([$value, ...$args], 0, max(1, $accepted_args));
            $value = $callback(...$call_args);
        }
    }

    return $value;
}

function get_the_ID()
{
    return $GLOBALS['__current_post_id'];
}

/**
 * Sets just enough of the real WordPress globals WordPressPostScope
 * snapshots/restores to exercise its own capture/restore logic in
 * isolation — not a faithful reproduction of setup_postdata()'s own date
 * parsing/pagination internals, which are exercised against the real
 * function in tests/integration.
 */
function setup_postdata($post)
{
    $GLOBALS['id'] = $post->ID;
    $GLOBALS['authordata'] = $post->post_author ?? null;
    $GLOBALS['currentday'] = '01.01.24';
    $GLOBALS['currentmonth'] = '01';
    $GLOBALS['page'] = 1;
    $GLOBALS['pages'] = [$post->post_content];
    $GLOBALS['multipage'] = 0;
    $GLOBALS['more'] = 1;
    $GLOBALS['numpages'] = 1;
}

/** @var mixed Settable directly by a test; defaults to null (no queried object). */
$GLOBALS['__queried_object'] = null;

/** Public post types only: what a placement may ever expose. */
function get_post_types($args = [], $output = 'names')
{
    if ('objects' === $output) {
        return [
            'post' => (object) ['name' => 'post', 'label' => 'Posts'],
            'page' => (object) ['name' => 'page', 'label' => 'Pages'],
        ];
    }

    return ['post' => 'post', 'page' => 'page'];
}

function get_queried_object()
{
    return $GLOBALS['__queried_object'];
}

function get_the_content()
{
    return $GLOBALS['__current_post_content'];
}

/**
 * Tests opt a post ID into "password required" by adding it to
 * $GLOBALS['__password_required_post_ids'] rather than this stub inspecting
 * any real post_password field, matching this bootstrap's existing pattern
 * of controllable globals for otherwise-unmodeled WP state.
 */
function post_password_required($post = null)
{
    $post_id = $post ?? $GLOBALS['__current_post_id'];

    return in_array((int) $post_id, $GLOBALS['__password_required_post_ids'], true);
}

function get_the_password_form($post = null)
{
    $post_id = $post ?? $GLOBALS['__current_post_id'];

    return '<form class="post-password-form" data-post-id="' . (int) $post_id . '"></form>';
}

function get_header($name = null, $args = array())
{
    do_action('get_header', $name, $args);
}

function get_footer($name = null, $args = array())
{
    do_action('get_footer', $name, $args);
}

function wp_body_open()
{
    do_action('wp_body_open');
}

/**
 * Reports the first requested template name found, matching a classic theme
 * that has it. Tests that need to simulate a located template calling
 * wp_body_open() itself set $GLOBALS['__locate_template_stub'] to a
 * callable ($load=true call sites only). Tests simulating a theme with no
 * matching file at all (e.g. a block theme's own missing header.php) set
 * $GLOBALS['__locate_template_missing'] = true.
 */
function locate_template($template_names, $load = false, $require_once = true, $args = array())
{
    if ($load && isset($GLOBALS['__locate_template_stub'])) {
        ($GLOBALS['__locate_template_stub'])($template_names);
    }

    if (!empty($GLOBALS['__locate_template_missing'])) {
        return '';
    }

    $template_names = (array) $template_names;

    return $template_names ? (string) reset($template_names) : '';
}

function get_option($key, $default_value = false)
{
    return array_key_exists($key, $GLOBALS['__wp_options']) ? $GLOBALS['__wp_options'][$key] : $default_value;
}

function update_option($key, $value, $autoload = null)
{
    $GLOBALS['__wp_options'][$key] = $value;

    return true;
}

function add_option($key, $value = '', $deprecated = '', $autoload = 'yes')
{
    if (array_key_exists($key, $GLOBALS['__wp_options'])) {
        return false;
    }

    $GLOBALS['__wp_options'][$key] = $value;

    return true;
}

function delete_option($key)
{
    unset($GLOBALS['__wp_options'][$key]);

    return true;
}

/**
 * Minimal transient stub with real expiry semantics (a get() past its TTL
 * is treated as absent and cleaned up), needed by ResolverCache's own
 * expiring rebuild lock. Migrator's own expiring lock (task 11.6) is the
 * atomic $wpdb-backed DatabaseLease, not a transient.
 */
function get_transient($key)
{
    if (!array_key_exists($key, $GLOBALS['__wp_transients'] ?? [])) {
        return false;
    }

    [$value, $expires_at] = $GLOBALS['__wp_transients'][$key];

    if ($expires_at !== 0 && $expires_at < time()) {
        unset($GLOBALS['__wp_transients'][$key]);

        return false;
    }

    return $value;
}

function set_transient($key, $value, $expiration = 0)
{
    $GLOBALS['__wp_transients'][$key] = [$value, $expiration > 0 ? time() + $expiration : 0];

    return true;
}

function delete_transient($key)
{
    unset($GLOBALS['__wp_transients'][$key]);

    return true;
}

function load_plugin_textdomain($domain, $deprecated = false, $plugin_rel_path = false)
{
    return true;
}

function __($text, $domain = 'default')
{
    return $text;
}

function esc_html__($text, $domain = 'default')
{
    return $text;
}

function esc_html($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_attr($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_url($url)
{
    return htmlspecialchars((string) $url, ENT_QUOTES);
}

function wp_parse_args($args, $defaults = [])
{
    return array_merge($defaults, (array) $args);
}

function sanitize_text_field($str)
{
    $filtered = trim(preg_replace('/[\r\n\t ]+/', ' ', wp_strip_all_tags((string) $str)));

    return preg_replace('/%[a-fA-F0-9]{2}/', '', $filtered) === $filtered ? $filtered : $filtered;
}

function wp_strip_all_tags($text)
{
    return trim(strip_tags(preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text)));
}

function sanitize_textarea_field($str)
{
    return trim(wp_strip_all_tags($str));
}

function sanitize_email($email)
{
    return filter_var((string) $email, FILTER_SANITIZE_EMAIL) ?: '';
}

function sanitize_user($username)
{
    return preg_replace('/[^a-zA-Z0-9 _.\-@]/', '', (string) $username);
}

function sanitize_url($url)
{
    return filter_var((string) $url, FILTER_SANITIZE_URL) ?: '';
}

function sanitize_key($key)
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
}

function sanitize_title($title)
{
    $title = strtolower(wp_strip_all_tags((string) $title));
    $title = preg_replace('/[^a-z0-9\s\-_]/', '', $title);

    return trim(preg_replace('/[\s\-]+/', '-', $title), '-');
}

function sanitize_file_name($filename)
{
    return preg_replace('/[^A-Za-z0-9._\-]/', '', (string) $filename);
}

function sanitize_mime_type($mime_type)
{
    return preg_replace('/[^\-+*.a-zA-Z0-9\/]/', '', (string) $mime_type);
}

function wp_kses_post($content)
{
    return strip_tags((string) $content, '<a><b><strong><em><i><p><br><ul><ol><li><img><h1><h2><h3><h4><h5><h6><blockquote><code><pre>');
}

function get_post_meta($post_id, $key = '', $single = false)
{
    $value = $GLOBALS['__wp_post_meta'][$post_id][$key] ?? '';

    return $single ? $value : ($value === '' ? [] : [$value]);
}

function update_post_meta($post_id, $key, $value)
{
    $GLOBALS['__wp_post_meta'][$post_id][$key] = $value;

    return true;
}

/**
 * Minimal stand-in for WordPress's post object: only the public properties
 * PostLoopItem actually reads, faithful to the real class's shape for those.
 */
/**
 * Minimal stand-in for WordPress's query object: the query vars a resolver
 * reads plus the empty result a query-building caller expects. Every
 * construction's vars are recorded in $GLOBALS['__wp_query_args'] so a
 * test can assert exactly what a caller asked WordPress for.
 */
class WP_Query
{
    public $query_vars = [];
    public $posts = [];
    public $found_posts = 0;
    public $max_num_pages = 0;

    public function __construct($query = [])
    {
        $this->query_vars = is_array($query) ? $query : [];
        $GLOBALS['__wp_query_args'][] = $this->query_vars;
    }

    public function get($query_var, $default_value = '')
    {
        return $this->query_vars[$query_var] ?? $default_value;
    }
}

class WP_Post
{
    public $ID;
    public $post_title = '';
    public $post_content = '';
    public $post_excerpt = '';
    public $post_type = 'post';

    public function __construct(int $id, array $data = [])
    {
        $this->ID = $id;

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * Minimal stand-in for WordPress's term object: only the public properties
 * TermLoopItem actually reads, faithful to the real class's shape for those.
 */
class WP_Term
{
    public $term_id;
    public $name = '';
    public $slug = '';
    public $taxonomy = 'category';
    public $count = 0;

    public function __construct(int $id, string $taxonomy = 'category', array $data = [])
    {
        $this->term_id = $id;
        $this->taxonomy = $taxonomy;

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * Minimal stand-in for WordPress's taxonomy object: the `name` and `labels`
 * members the taxonomy-scoped conditions read, faithful to the real class's
 * shape for those.
 */
class WP_Taxonomy
{
    public $name = '';
    public $label = '';
    public $labels;

    public function __construct(string $name, string $singular = '')
    {
        $this->name = $name;
        $this->label = $singular !== '' ? $singular : $name;
        $this->labels = (object) ['singular_name' => $this->label];
    }
}

/**
 * Minimal stand-in for WordPress's user object: only the public properties
 * UserLoopItem actually reads, faithful to the real class's shape for those.
 */
class WP_User
{
    public $ID;
    public $display_name = '';
    public $user_login = '';
    public $user_email = '';

    public function __construct(int $id, array $data = [])
    {
        $this->ID = $id;

        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}

/**
 * Minimal stand-in for WordPress's REST response class: only what
 * Elemacy\Http\Response (which extends it) needs to be declarable, so
 * any code path that merely references that class — e.g. a class constant,
 * as ValidationException::with_errors() does — can load without a real
 * WordPress REST API present.
 */
class WP_REST_Response
{
    protected $data;
    protected $status;
    protected $headers;

    public function __construct($data = null, $status = 200, $headers = [])
    {
        $this->data = $data;
        $this->status = $status;
        $this->headers = $headers;
    }

    public function get_data()
    {
        return $this->data;
    }

    public function set_data($data): void
    {
        $this->data = $data;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function set_status($status): void
    {
        $this->status = $status;
    }

    public function get_headers()
    {
        return $this->headers;
    }
}

function map_deep($value, $callback)
{
    if (is_array($value)) {
        foreach ($value as $index => $item) {
            $value[$index] = map_deep($item, $callback);
        }

        return $value;
    }

    if (is_object($value)) {
        foreach (get_object_vars($value) as $property_name => $property_value) {
            $value->$property_name = map_deep($property_value, $callback);
        }

        return $value;
    }

    return $callback($value);
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/harness.php';
