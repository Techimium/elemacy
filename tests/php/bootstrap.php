<?php

/**
 * Test bootstrap: a minimal in-memory WordPress stub layer plus the plugin's
 * composer autoloader, so business logic can be executed without a WordPress
 * install. Only the functions the tested classes actually call are stubbed,
 * each faithful to core's documented behavior for the inputs used in tests.
 */

define('ABSPATH', '/tmp/wp/');
define('DAY_IN_SECONDS', 86400);
define('HOUR_IN_SECONDS', 3600);

$GLOBALS['__wp_options'] = [];
$GLOBALS['__wp_actions'] = [];
$GLOBALS['__wp_actions_fired'] = [];
$GLOBALS['__current_post_id'] = 0;

function add_action($hook, $callback, $priority = 10, $accepted_args = 1)
{
    $GLOBALS['__wp_actions'][$hook][$priority][] = [$callback, $accepted_args];

    return true;
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
 * Always reports nothing found, matching a theme with no matching template
 * file. Tests that need to simulate a located template calling wp_body_open()
 * itself set $GLOBALS['__locate_template_stub'] to a callable.
 */
function locate_template($template_names, $load = false, $require_once = true, $args = array())
{
    if ($load && isset($GLOBALS['__locate_template_stub'])) {
        ($GLOBALS['__locate_template_stub'])();
    }

    return '';
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

function delete_option($key)
{
    unset($GLOBALS['__wp_options'][$key]);

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
