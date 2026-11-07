<?php

/**
 * The Archive Title dynamic tag (openspec dynamic-tags/archive-title): the
 * search heading on search pages, and the WordPress archive title, with the
 * Remove Prefix switch, everywhere else. Real WordPress requests are covered in
 * tests/integration/archive-title-preview.php.
 */

use Elemacy\Modules\DynamicTags\Tags\Archive\ArchiveTitle;

if (!function_exists('get_search_query')) {
    /** Mirrors WordPress: escaped unless asked otherwise. */
    function get_search_query($escaped = true)
    {
        $query = (string) ($GLOBALS['__search_query'] ?? '');

        return $escaped ? esc_attr($query) : $query;
    }
}

if (!function_exists('get_the_archive_title')) {
    function get_the_archive_title()
    {
        return $GLOBALS['__archive_title'] ?? 'Archives';
    }
}

/** Renders the tag with the given settings on a simulated request. */
function render_archive_title_fixture(array $settings, bool $is_search, string $search_query = '', string $archive_title = 'Archives'): string
{
    $GLOBALS['__wp_is_search']  = $is_search;
    $GLOBALS['__search_query']  = $search_query;
    $GLOBALS['__archive_title'] = $archive_title;

    ob_start();
    (new ArchiveTitle(['settings' => $settings]))->render();
    $output = ob_get_clean();

    unset($GLOBALS['__wp_is_search'], $GLOBALS['__search_query'], $GLOBALS['__archive_title']);

    return $output;
}

check('Archive Title shows "Search Results for: term" on a search page, with Remove Prefix on', static function () {
    return 'Search Results for: coffee' === render_archive_title_fixture(['remove_prefix' => 'yes'], true, 'coffee');
});

check('Archive Title shows the same search heading with Remove Prefix off', static function () {
    return 'Search Results for: coffee' === render_archive_title_fixture(['remove_prefix' => ''], true, 'coffee');
});

check('Archive Title never shows the generic "Archives" word on a search page', static function () {
    return false === strpos(render_archive_title_fixture(['remove_prefix' => 'yes'], true, 'x', 'Archives'), 'Archives');
});

check('Archive Title outputs a search term containing markup as inert text', static function () {
    $output = render_archive_title_fixture(['remove_prefix' => 'yes'], true, '<script>alert(1)</script>');

    return false === strpos($output, '<script') && false !== strpos($output, 'Search Results for: ');
});

check('Archive Title keeps stripping the prefix from a non-search archive when Remove Prefix is on', static function () {
    return 'News' === render_archive_title_fixture(['remove_prefix' => 'yes'], false, '', 'Category: News');
});

check('Archive Title keeps the prefix on a non-search archive when Remove Prefix is off', static function () {
    return 'Category: News' === render_archive_title_fixture(['remove_prefix' => ''], false, '', 'Category: News');
});

check('Archive Title leaves a non-search archive title without a prefix untouched', static function () {
    return 'Archives' === render_archive_title_fixture(['remove_prefix' => 'yes'], false, '', 'Archives');
});
