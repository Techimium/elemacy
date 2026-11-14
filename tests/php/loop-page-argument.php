<?php

/**
 * The URL argument a loop widget paginates through when it shares a singular
 * page with another paginated widget
 * (`elemacy-page-<id>`): building its links, reading its page, which widgets
 * use it, and what PaginationRenderer hands paginate_links() with and without
 * link arguments. Required by run.php.
 */

use Elemacy\Loop\Pagination\PageArgument;
use Elemacy\Loop\Pagination\PageLinks;
use Elemacy\Loop\Pagination\PaginatedWidgets;
use Elemacy\Loop\Pagination\PaginationRenderer;
use Elemacy\Loop\Rendering\ItemPresenterRegistry;

if (!function_exists('get_query_var')) {
    function get_query_var($name, $default_value = '')
    {
        return $GLOBALS['__query_vars'][$name] ?? $default_value;
    }
}

if (!function_exists('paginate_links')) {
    // Core's own substitution of `%_%` and `%#%`, enough for the cases below.
    function paginate_links($args = '')
    {
        $GLOBALS['__paginate_calls'][] = $args;

        $base = $args['base'] ?? 'DEFAULT/%_%';
        $format = $args['format'] ?? '?paged=%#%';
        $link = static function (int $n) use ($base, $format): string {
            return str_replace('%#%', (string) $n, str_replace('%_%', 1 === $n ? '' : $format, $base));
        };

        $links = [];
        if (!empty($args['prev_next']) && $args['current'] > 1) {
            $links[] = '<a class="prev page-numbers" href="' . $link($args['current'] - 1) . '">' . $args['prev_text'] . '</a>';
        }
        for ($n = 1; $n <= $args['total']; $n++) {
            $links[] = $n === $args['current']
                ? '<span class="page-numbers current">' . $n . '</span>'
                : '<a class="page-numbers" href="' . $link($n) . '">' . $n . '</a>';
        }
        if (!empty($args['prev_next']) && $args['current'] < $args['total']) {
            $links[] = '<a class="next page-numbers" href="' . $link($args['current'] + 1) . '">' . $args['next_text'] . '</a>';
        }

        return $links;
    }
}

check('PageArgument names the argument elemacy-page-<widget id> and keeps only safe characters of the id', static function () {
    return 'elemacy-page-12e5ce7' === PageArgument::name('12e5ce7')
        && 'elemacy-page-abc' === PageArgument::name('a<b>c')
        && 'elemacy-page-' === PageArgument::PREFIX . '';
});

check('PageArgument::url adds the widget\'s argument for page 2 and keeps every other argument, other widgets\' pages and the fragment', static function () {
    $url = 'http://example.test/blog/?s=a%20b&elemacy-page-other=3&tag[]=x#grid';

    return 'http://example.test/blog/?s=a%20b&elemacy-page-other=3&tag[]=x&elemacy-page-ab12=2#grid' === PageArgument::url($url, 'ab12', 2)
        && 'http://example.test/blog/?elemacy-page-ab12=4' === PageArgument::url('http://example.test/blog/', 'ab12', 4);
});

check('PageArgument::url removes the widget\'s own argument for page 1 and replaces it for another page, leaving a clean URL', static function () {
    $on_page_three = 'http://example.test/blog/?elemacy-page-ab12=3';

    return 'http://example.test/blog/' === PageArgument::url($on_page_three, 'ab12', 1)
        && 'http://example.test/blog/?elemacy-page-ab12=2' === PageArgument::url($on_page_three, 'ab12', 2)
        && 'http://example.test/blog/?s=x&elemacy-page-other=2' === PageArgument::url('http://example.test/blog/?elemacy-page-ab12=3&s=x&elemacy-page-other=2', 'ab12', 1)
        && 'http://example.test/page/2/' === PageArgument::url('http://example.test/page/2/', 'ab12', 1);
});

check('PageArgument::read reads only the widget\'s own argument as a positive whole number, and anything else is page 1', static function () {
    $query = ['elemacy-page-ab12' => '3', 'elemacy-page-cd34' => '5', 'paged' => '9', 'page' => '8'];

    return 3 === PageArgument::read('ab12', $query)
        && 5 === PageArgument::read('cd34', $query)
        && 1 === PageArgument::read('ef56', $query)
        && 1 === PageArgument::read('ab12', ['paged' => '9'])
        && 1 === PageArgument::read('ab12', ['elemacy-page-ab12' => '0'])
        && 1 === PageArgument::read('ab12', ['elemacy-page-ab12' => '-4'])
        && 1 === PageArgument::read('ab12', ['elemacy-page-ab12' => 'two'])
        && 1 === PageArgument::read('ab12', ['elemacy-page-ab12' => '2.5'])
        && 1 === PageArgument::read('ab12', ['elemacy-page-ab12' => ['2']])
        && 1 === PageArgument::read('ab12', ['elemacy-page-ab12' => ''])
        && 1 === PageArgument::read('ab12', ['elemacy-page-ab12' => '99999999999999999999']);
});

check('PageArgument::read takes $_GET when no query is given', static function () {
    $saved = $_GET;
    $_GET = ['elemacy-page-ab12' => '4'];

    try {
        return 4 === PageArgument::read('ab12') && 1 === PageArgument::read('cd34');
    } finally {
        $_GET = $saved;
    }
});

check('PaginationRenderer with the widget\'s own argument links page 1 without the argument and other pages with it, keeping the page\'s other arguments', static function () {
    $GLOBALS['__paginate_calls'] = [];
    $renderer = new PaginationRenderer();

    ob_start();
    $renderer->render(
        ['pagination_type' => 'numbers_and_prev_next'],
        3,
        2,
        PageLinks::argument('http://example.test/blog/?s=a&elemacy-page-other=3', 'ab12')
    );
    $html = ob_get_clean();

    return false !== strpos($html, '<a class="prev page-numbers" href="http://example.test/blog/?s=a&elemacy-page-other=3">')
        && false !== strpos($html, '<a class="next page-numbers" href="http://example.test/blog/?s=a&elemacy-page-other=3&elemacy-page-ab12=3">')
        && false !== strpos($html, '<a class="page-numbers" href="http://example.test/blog/?s=a&elemacy-page-other=3">1</a>')
        && false !== strpos($html, '<a class="page-numbers" href="http://example.test/blog/?s=a&elemacy-page-other=3&elemacy-page-ab12=3">3</a>')
        && false === strpos($html, 'elemacy-page-ab12=1');
});

check('PaginationRenderer with the widget\'s own argument builds the Load More link to the widget\'s next page', static function () {
    $renderer = new PaginationRenderer();

    ob_start();
    $renderer->render(['pagination_type' => 'load_more'], 4, 2, PageLinks::argument('http://example.test/', 'ab12'));
    $html = ob_get_clean();

    return false !== strpos($html, 'class="page-numbers elemacy-load-more" rel="next" href="http://example.test/?elemacy-page-ab12=3"');
});

check('PaginationRenderer without link arguments (archives, Current Query) leaves paginate_links() on the site\'s own page URLs', static function () {
    $GLOBALS['__paginate_calls'] = [];
    $GLOBALS['__query_vars'] = ['paged' => 2];
    $renderer = new PaginationRenderer();

    ob_start();
    $renderer->render(['pagination_type' => 'prev_next'], 3);
    ob_end_clean();
    $args = $GLOBALS['__paginate_calls'][0] ?? [];
    unset($GLOBALS['__query_vars']);

    return 2 === ($args['current'] ?? null) && !isset($args['base']) && !isset($args['format']);
});

check('PageLinks::argument keeps the page\'s query and fragment around the argument', static function () {
    $links = PageLinks::argument('http://example.test/blog/?s=a&elemacy-page-ab12=3#grid', 'ab12');

    return 'http://example.test/blog/?s=a%_%#grid' === $links['base'] && '&elemacy-page-ab12=%#%' === $links['format']
        && '?elemacy-page-ab12=%#%' === PageLinks::argument('http://example.test/blog/', 'ab12')['format'];
});

$saved_grid = static function (string $id, array $settings): array {
    return ['id' => $id, 'elType' => 'widget', 'widgetType' => 'fixture-grid', 'settings' => $settings, 'elements' => []];
};

check('PaginatedWidgets lists the saved loop widgets that paginate, at any depth, and ignores other widgets and widgets without pagination', static function () use ($saved_grid) {
    ItemPresenterRegistry::instance()->register('fixture-grid', RegistryFixturePresenter::class);
    $GLOBALS['__wp_post_meta'][9101]['_elementor_data'] = json_encode([
        ['id' => 'c1', 'elType' => 'container', 'settings' => [], 'elements' => [
            $saved_grid('g1', ['pagination_type' => 'numbers']),
            ['id' => 'c2', 'elType' => 'container', 'settings' => [], 'elements' => [$saved_grid('g2', ['pagination_type' => 'load_more'])]],
            $saved_grid('g3', []),
            ['id' => 'h1', 'elType' => 'widget', 'widgetType' => 'heading', 'settings' => ['pagination_type' => 'numbers'], 'elements' => []],
        ]],
    ]);

    $ids = array_column(PaginatedWidgets::in_document(9101), 'id');
    sort($ids);

    return ['g1', 'g2'] === $ids && [] === PaginatedWidgets::in_document(9999);
});

check('no widget pages through its own argument off a singular page, however it loads its pages', static function () {
    return !PaginatedWidgets::uses_own_argument(9101, ['pagination_type' => 'numbers'])
        && !PaginatedWidgets::uses_own_argument(9101, ['pagination_type' => 'load_more'])
        && !PaginatedWidgets::uses_own_argument(9101, ['pagination_type' => 'numbers', 'pagination_ajax' => 'yes'])
        && !PaginatedWidgets::uses_own_argument(9101, ['pagination_type' => '']);
});

check('the loop widget reads its page from the site\'s URL unless the widget uses its own argument, and a page-URL source always does', static function () {
    $source = file_get_contents(dirname(__DIR__, 2) . '/src/Modules/LoopBuilder/Widgets/LoopWidget.php');
    preg_match('/function requested_page\(.*?\n    }\n/s', $source, $method);
    $body = $method[0] ?? '';

    return false !== strpos($body, 'PaginatedWidgets::uses_own_argument($this->current_owner_id(), $settings)')
        && false !== strpos($body, 'PageArgument::read($this->get_id())')
        && 1 === substr_count($body, "get_query_var('paged')")
        && false !== strpos($source, '$this->page_link_args($settings, $source)');
});
