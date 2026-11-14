<?php

namespace Elemacy\Loop\Pagination;

defined('ABSPATH') || exit;

/**
 * The URL argument one loop widget paginates through, `elemacy-page-<id>`
 * where `<id>` is the widget's Elementor element id: the single place that
 * names it, reads it and builds URLs with it.
 *
 * A widget pages through its own argument (never the site's paged URLs) when
 * the visitor is on a singular page and the widget's own document holds two
 * or more paginated loop widgets, so the widgets move independently. Page one
 * is the clean URL: its link removes the argument.
 *
 * @internal
 */
final class PageArgument
{
    public const PREFIX = 'elemacy-page-';

    /** The argument name of the widget with this element id. */
    public static function name(string $widget_id): string
    {
        return self::PREFIX . preg_replace('/[^A-Za-z0-9_-]/', '', $widget_id);
    }

    /**
     * The page a request asked of a widget: its own argument only, at least
     * one, and one for anything that is not a plain positive whole number.
     *
     * @param string     $widget_id
     * @param array|null $query     The request's query arguments; `$_GET` when omitted.
     */
    public static function read(string $widget_id, ?array $query = null): int
    {
        if (null === $query) {
            $query = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a public page number, read as a positive integer.
        }

        $value = $query[self::name($widget_id)] ?? null;

        if (!is_scalar($value) || !preg_match('/^\d{1,9}$/', (string) $value)) {
            return 1;
        }

        return max(1, (int) $value);
    }

    /**
     * The URL of one page of a widget: the given URL with the widget's own
     * argument set to the page, or removed for page one. Every other query
     * argument, including other widgets' page arguments, and the fragment are
     * kept as they were.
     */
    public static function url(string $url, string $widget_id, int $page): string
    {
        $name = self::name($widget_id);
        $fragment = '';
        $query = '';

        $position = strpos($url, '#');
        if (false !== $position) {
            $fragment = substr($url, $position);
            $url = substr($url, 0, $position);
        }

        $position = strpos($url, '?');
        if (false !== $position) {
            $query = substr($url, $position + 1);
            $url = substr($url, 0, $position);
        }

        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            if ('' !== $pair && urldecode(explode('=', $pair, 2)[0]) !== $name) {
                $pairs[] = $pair;
            }
        }

        if ($page > 1) {
            $pairs[] = $name . '=' . $page;
        }

        return $url . ($pairs ? '?' . implode('&', $pairs) : '') . $fragment;
    }

    /** The URL of the request being served, with scheme and host. */
    public static function current_url(): string
    {
        $parts = wp_parse_url(home_url('/'));
        $origin = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised by esc_url_raw() below.

        return esc_url_raw($origin . '/' . ltrim($uri, '/'));
    }
}
