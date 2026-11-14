<?php

/**
 * Post Grid and Post Carousel: what a saved widget falls back to when it holds
 * a card part or image position that is not offered, and the one page size
 * every post source reads. Required by run.php.
 */

use Elemacy\Loop\Contracts\SharedPageSizeSourceInterface;
use Elemacy\Loop\LoopContext;
use Elemacy\Loop\Items\PostLoopItem;
use Elemacy\Loop\Rendering\ItemPlacement;
use Elemacy\Loop\Rendering\LoopItemScope;
use Elemacy\Loop\Placement\HostFacts;
use Elemacy\Loop\Sources\ManualPosts\ManualPostsPolicy;
use Elemacy\Loop\Sources\ManualPosts\ManualPostsQuery;
use Elemacy\Loop\Sources\ManualPosts\ManualPostsSettings;
use Elemacy\Loop\Sources\Posts\PostsPolicy;
use Elemacy\Loop\Sources\Posts\PostsQuery;
use Elemacy\Loop\Sources\Posts\PostsSettings;
use Elemacy\Loop\Sources\RelatedPosts\RelatedPostsPolicy;
use Elemacy\Loop\Sources\RelatedPosts\RelatedPostsQuery;
use Elemacy\Loop\Sources\RelatedPosts\RelatedPostsSettings;
use Elemacy\Modules\LoopBuilder\DataSources\CurrentQueryDataSource;
use Elemacy\Modules\LoopBuilder\DataSources\ManualPostsDataSource;
use Elemacy\Modules\LoopBuilder\DataSources\PostsDataSource;
use Elemacy\Modules\LoopBuilder\DataSources\RelatedPostsDataSource;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardDefinition;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardParts;
use Elemacy\Modules\LoopBuilder\PostCard\PostCardPresenter;
use Elemacy\Modules\LoopBuilder\Widgets\PostWidget;

check('PostCardDefinition drops saved rows of a part type that does not exist and keeps the rest in order', static function () {
    $definition = new PostCardDefinition([PostCardDefinition::PARTS => [
        ['part' => 'title', 'zone' => 'body'],
        ['part' => 'divider', 'zone' => 'body', 'divider_style' => 'space'],
        ['part' => 'custom_text', 'zone' => 'footer', 'custom_text_content' => 'Hello {title}'],
        ['part' => 'button', 'zone' => 'footer'],
    ]]);

    return ['title', 'button'] === array_column($definition->rows(), 'part');
});

check('a saved row with no part is a Title row: Elementor saves nothing for a value left at its default, even inside a repeater row', static function () {
    // A Title row as the editor saves it, its part and zone left at their defaults.
    $rows = (new PostCardDefinition([PostCardDefinition::PARTS => [
        ['_id' => 'f72fb61', 'meta_separator' => '·'],
        ['part' => 'meta', '_id' => '0725a6b'],
    ]]))->rows();

    return ['title', 'meta'] === array_column($rows, 'part')
        && 'body' === $rows[0]['zone']
        && 'h3' === $rows[0]['title_tag']
        && 'f72fb61' === $rows[0]['_id']
        && 'date' === $rows[1]['meta_item'];
});

check('the card offers exactly the eight parts,and a row gets only its own part\'s defaults', static function () {
    $author = (new PostCardDefinition([PostCardDefinition::PARTS => [['part' => 'author', 'author_bio' => 'yes']]]))->rows()[0];

    return ['image', 'title', 'meta', 'excerpt', 'terms', 'author', 'button', 'custom_field'] === array_keys(PostCardParts::all())
        && ['author_avatar', 'author_avatar_size', 'author_name', 'author_link'] === array_keys(PostCardParts::get('author')->defaults())
        && 'yes' === $author['author_avatar'];
});

check('an image position that is not offered has no rule of its own, and the stylesheet\'s own fallbacks are the Top layout', static function () {
    $positions = (new \ReflectionClassConstant(PostWidget::class, 'CARD_IMAGE_POSITIONS'))->getValue();

    if (['top', 'left', 'right', 'background'] !== array_keys($positions)) {
        return false;
    }

    // Elementor emits nothing for a saved value its dictionary lacks, so the
    // card then renders from these fallbacks alone.
    preg_match_all('/(--elemacy-card-[a-z-]+):\s*([^;]+);/', $positions['top'], $top, PREG_SET_ORDER);
    $css = (string) file_get_contents(ELEMACY_PATH . 'src/Modules/LoopBuilder/assets/css/post-card.css');
    $checked = 0;

    foreach ($top as [, $property, $value]) {
        preg_match_all('/var\(' . preg_quote($property, '/') . ',\s*([^()]+)\)/', $css, $fallbacks);

        if (!$fallbacks[1] || [trim($value)] !== array_values(array_unique(array_map('trim', $fallbacks[1])))) {
            return false;
        }
        $checked++;
    }

    foreach ($positions as $rules) {
        preg_match_all('/(--elemacy-card-[a-z-]+):/', $rules, $set);
        if ($set[1] !== array_column($top, 1)) {
            return false;
        }
    }

    return 9 === $checked;
});

check('Posts, Manual Selection and Related Posts read one saved page size; Current Query has none', static function () {
    return 'posts_per_page' === PostsSettings::PER_PAGE
        && PostsSettings::PER_PAGE === ManualPostsSettings::PER_PAGE
        && PostsSettings::PER_PAGE === RelatedPostsSettings::PER_PAGE
        && 6 === PostsSettings::DEFAULT_PER_PAGE
        && 6 === RelatedPostsSettings::DEFAULT_PER_PAGE
        && new PostsDataSource() instanceof SharedPageSizeSourceInterface
        && new ManualPostsDataSource() instanceof SharedPageSizeSourceInterface
        && new RelatedPostsDataSource() instanceof SharedPageSizeSourceInterface
        && !(new CurrentQueryDataSource() instanceof SharedPageSizeSourceInterface);
});

check('Manual Selection resolves the same page size from raw saved settings (a later page) as with the default filled in (the first page)', static function () {
    $policy = new ManualPostsPolicy();
    $raw = [ManualPostsSettings::IDS => [5, 3, 9]];
    $filled = $raw + [PostsSettings::PER_PAGE => PostsSettings::DEFAULT_PER_PAGE];

    return 6 === $policy->get_public_settings($raw, [], 2)[ManualPostsSettings::PER_PAGE]
        && $policy->get_public_settings($filled, [], 2) === $policy->get_public_settings($raw, [], 2)
        && 2 === $policy->get_public_settings($raw + [PostsSettings::PER_PAGE => 2], [], 1)[ManualPostsSettings::PER_PAGE]
        && [5, 3, 9] === $policy->get_public_settings($raw, [], 1)[ManualPostsSettings::IDS];
});

check('Related Posts resolves the same page size from raw saved settings (a later page) as with the default filled in (the first page)', static function () {
    $policy = new RelatedPostsPolicy();
    $context = [HostFacts::POST_ID => 7];
    $filled = [PostsSettings::PER_PAGE => PostsSettings::DEFAULT_PER_PAGE];

    return 6 === $policy->get_public_settings([], $context, 2)[RelatedPostsSettings::PER_PAGE]
        && $policy->get_public_settings($filled, $context, 2) === $policy->get_public_settings([], $context, 2)
        && 2 === $policy->get_public_settings([PostsSettings::PER_PAGE => 2], $context, 1)[RelatedPostsSettings::PER_PAGE];
});

check('a Posts offset is kept on later pages: each page skips the offset plus the pages before it', static function () {
    $query = new PostsQuery();
    $saved = [PostsSettings::POST_TYPE => 'post', PostsSettings::OFFSET => 4, PostsSettings::PER_PAGE => 3];
    $first = $query->sanitize_ajax_settings($saved, 1);
    $third = $query->sanitize_ajax_settings($saved, 3);

    return 4 === $first[PostsSettings::OFFSET]
        && 10 === $third[PostsSettings::OFFSET]
        && 4 === $third[PostsSettings::OFFSET_BASE]
        && !isset($query->sanitize_ajax_settings([PostsSettings::POST_TYPE => 'post'], 2)[PostsSettings::OFFSET]);
});

check('a Posts offset counts the same pages in the editor, which runs the saved settings, as for visitors on every page', static function () {
    $saved = [
        PostsSettings::POST_TYPE => 'post', PostsSettings::OFFSET => 4, PostsSettings::PER_PAGE => 3,
        PostsSettings::ORDER_BY => 'date', PostsSettings::ORDER => 'DESC', PostsSettings::EXCLUDE_CURRENT => '',
        'pagination_type' => 'numbers',
    ];
    $GLOBALS['__wp_query_found_posts'] = 10;

    try {
        $query = new PostsQuery();
        $policy = new PostsPolicy($query);
        $editor = $query->get_items($saved)->max_num_pages;
        $first = $query->get_items($policy->get_public_settings($saved, [], 1))->max_num_pages;
        $second = $query->get_items($policy->get_public_settings($saved, [], 2))->max_num_pages;
    } finally {
        unset($GLOBALS['__wp_query_found_posts']);
    }

    // Ten posts, four skipped, three a page.
    return 2 === $editor && 2 === $first && 2 === $second;
});

check('a negative Posts offset skips the same posts as WordPress does on page one, so a later page continues after them', static function () {
    $query = new PostsQuery();
    $saved = [PostsSettings::POST_TYPE => 'post', PostsSettings::OFFSET => -4, PostsSettings::PER_PAGE => 3];

    return 4 === $query->sanitize_ajax_settings($saved, 1)[PostsSettings::OFFSET]
        && 7 === $query->sanitize_ajax_settings($saved, 2)[PostsSettings::OFFSET]
        && 4 === $query->sanitize_ajax_settings($saved, 2)[PostsSettings::OFFSET_BASE];
});

/* ── Page size ─────────────────────────────────────────────────────── */

check('the post page size is a whole number from 1 to 100, and anything empty, zero, negative or not a number is the default 6', static function () {
    $expected = [
        [5, 5], ['5', 5], [1, 1], [100, 100], [101, 100], [500, 100], ['500', 100], [2.9, 2], ['1e9', 100],
        ['', 6], [null, 6], [0, 6], ['0', 6], [-1, 6], [-20, 6], [0.5, 6], ['abc', 6], ['3 posts', 6], [[], 6], [true, 6], [NAN, 6],
    ];

    foreach ($expected as [$saved, $size]) {
        if ($size !== PostsSettings::page_size($saved)) {
            return false;
        }
    }

    return true;
});

if (!function_exists('is_taxonomy_viewable')) {
    function is_taxonomy_viewable($taxonomy)
    {
        return true;
    }
}

/** The page size each post source asks WordPress for, on the first page (defaults filled in) and from the raw saved settings of a later page. */
function elemacy_page_size_runs($saved): array
{
    $query_size = static function (callable $run): int {
        $GLOBALS['__wp_query_args'] = [];
        $run();

        return (int) end($GLOBALS['__wp_query_args'])['posts_per_page'];
    };
    $context = [HostFacts::POST_ID => 7];
    $GLOBALS['__current_post'] = new WP_Post(7);
    $posts_query = new PostsQuery();
    $posts_policy = new PostsPolicy($posts_query);
    $manual_query = new ManualPostsQuery();
    $manual_policy = new ManualPostsPolicy();
    $related_query = new RelatedPostsQuery();
    $related_policy = new RelatedPostsPolicy();
    $size = [PostsSettings::PER_PAGE => $saved];
    $posts = [PostsSettings::POST_TYPE => 'post', PostsSettings::EXCLUDE_CURRENT => ''];
    $manual = [ManualPostsSettings::IDS => [5, 3, 9]];
    $related = [RelatedPostsSettings::FALLBACK => 'yes', RelatedPostsSettings::CURRENT_POST_ID => 7];

    try {
        return [
            'posts editor' => $query_size(static fn () => $posts_query->get_items($posts + $size + PostsSettings::defaults())),
            'posts first' => $query_size(static fn () => $posts_query->get_items($posts_policy->get_public_settings($posts + $size, [], 1))),
            'posts later' => $query_size(static fn () => $posts_query->get_items($posts_policy->get_public_settings($posts + $size, [], 2))),
            'manual editor' => $query_size(static fn () => $manual_query->get_items($manual + $size)),
            'manual first' => $query_size(static fn () => $manual_query->get_items($manual_policy->get_public_settings($manual + $size, [], 1))),
            'manual later' => $query_size(static fn () => $manual_query->get_items($manual_policy->get_public_settings($manual + $size, [], 2))),
            'related editor' => $query_size(static fn () => $related_query->get_items($related + $size)),
            'related first' => $query_size(static fn () => $related_query->get_items($related_policy->get_public_settings($related + $size, $context, 1))),
            'related later' => $query_size(static fn () => $related_query->get_items($related_policy->get_public_settings($related + $size, $context, 2))),
        ];
    } finally {
        unset($GLOBALS['__current_post']);
    }
}

check('the editor preview, the first page and a later page run every post source with the same normalised page size, whatever was saved', static function () {
    foreach ([['', 6], [0, 6], [-4, 6], ['abc', 6], [500, 100], [101, 100], [1, 1], ['7', 7], [100, 100]] as [$saved, $size]) {
        $runs = elemacy_page_size_runs($saved);

        if (array_values(array_unique($runs)) !== [$size] || 9 !== count($runs)) {
            return false;
        }
    }

    return true;
});

check('a page size left out of the saved settings is the default on the editor preview, the first page and a later page alike', static function () {
    $GLOBALS['__wp_query_args'] = [];
    $manual = new ManualPostsQuery();
    $manual->get_items([ManualPostsSettings::IDS => [5]]);
    $policy = new ManualPostsPolicy();
    $manual->get_items($policy->get_public_settings([ManualPostsSettings::IDS => [5]], [], 2));

    return [6, 6] === array_column($GLOBALS['__wp_query_args'], 'posts_per_page');
});

/* ── Card markup ───────────────────────────────────────────────────── */

function elemacy_has(string $html, string $needle): bool
{
    return false !== strpos($html, $needle);
}

if (!function_exists('get_permalink')) {
    function get_permalink($post = 0)
    {
        return 'https://example.test/?p=' . $post->ID;
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post, $taxonomy)
    {
        return $GLOBALS['__post_terms'] ?? false;
    }
}

if (!function_exists('get_term_link')) {
    function get_term_link($term, $taxonomy = '')
    {
        return false;
    }
}

if (!function_exists('get_post_thumbnail_id')) {
    function get_post_thumbnail_id($post = null)
    {
        return $GLOBALS['__thumbnail_id'] ?? 0;
    }
}

if (!function_exists('wp_get_attachment_image')) {
    function wp_get_attachment_image($id, $size = 'thumbnail', $icon = false, $attr = '')
    {
        return '<img src="https://example.test/' . $id . '.jpg" alt="">';
    }
}

/**
 * One card for a post, as the first page and a later page both print it.
 *
 * @param array<int, array<string, mixed>> $rows
 * @return string Empty when the item did not render.
 */
function elemacy_card(array $rows, array $post = [], array $settings = []): string
{
    $item = new PostLoopItem(new WP_Post(11, $post + ['post_title' => 'A title']));
    $presenter = new PostCardPresenter(new LoopItemScope());

    ob_start();
    $rendered = $presenter->render_item($item, [PostCardDefinition::PARTS => $rows] + $settings, new ItemPlacement('1-abc', 'posts', 0, '', 2));
    $html = (string) ob_get_clean();

    return $rendered ? $html : '';
}

check('a card whose parts all render nothing for a post prints no article and no item wrapper', static function () {
    $title_only = [['part' => 'title']];

    return '' === elemacy_card($title_only, ['post_title' => ''])
        && '' === elemacy_card([], [])
        && '' === elemacy_card([['part' => 'divider']], [])
        && '' === elemacy_card($title_only, ['post_title' => ''], [PostCardDefinition::WHOLE_CARD_LINK => 'yes'])
        && elemacy_has(elemacy_card($title_only), '<article class="elemacy-post-card');
});

check('a card with an empty parts list has no rows, and the item scope drops a body that had nothing to show', static function () {
    $item = new PostLoopItem(new WP_Post(11));
    $scope = new LoopItemScope();
    $identity = new \Elemacy\Loop\Rendering\LoopRenderIdentity('1-abc', 'post-card', 'posts', '11', 0, '', '');

    ob_start();
    $dropped = $scope->render($item, $identity, 'posts', 0, 0, static function () {
        echo 'half';

        return false;
    });
    $none = (string) ob_get_clean();

    ob_start();
    $kept = $scope->render($item, $identity, 'posts', 0, 0, static function (): void {
        echo 'body';
    });
    $some = (string) ob_get_clean();

    return [] === (new PostCardDefinition([PostCardDefinition::PARTS => []]))->rows()
        && !$dropped && '' === $none
        && $kept && elemacy_has($some, 'body') && elemacy_has($some, 'data-elemacy-item="11"');
});

check('the whole-card overlay is its own pointer-only anchor when a visible linked Title is the keyboard stop', static function () {
    $html = elemacy_card([['part' => 'title'], ['part' => 'button']], [], [PostCardDefinition::WHOLE_CARD_LINK => 'yes']);

    return 1 === substr_count($html, 'class="elemacy-post-card__overlay"')
        && elemacy_has($html, 'class="elemacy-post-card__overlay" href="https://example.test/?p=11" tabindex="-1" aria-hidden="true"></a>')
        && elemacy_has($html, '<a class="elemacy-post-card__link" href="https://example.test/?p=11">A title</a>')
        && elemacy_has($html, 'elemacy-post-card--linked')
        && 1 === substr_count($html, 'elemacy-post-card__link');
});

check('the whole-card overlay is the focusable link with the post title as its name when the card has no Title row, an unlinked one, or one hidden on a device', static function () {
    $settings = [PostCardDefinition::WHOLE_CARD_LINK => 'yes'];
    $named = static fn (string $html): bool => elemacy_has($html, '<a class="elemacy-post-card__overlay" href="https://example.test/?p=11"><span class="elemacy-post-card__sr">A title</span></a>')
        && !elemacy_has($html, 'tabindex="-1"');

    return $named(elemacy_card([['part' => 'button']], [], $settings))
        && $named(elemacy_card([['part' => 'title', 'title_link' => ''], ['part' => 'button']], [], $settings))
        && $named(elemacy_card([['part' => 'title', 'row_hide_on' => ['mobile']], ['part' => 'button']], [], $settings))
        && $named(elemacy_card([['part' => 'title', '_id' => 'abc', 'row_hide_on' => ['mobile']]], [], $settings));
});

check('the whole-card overlay of an untitled post is a focusable link named (no title), like the Read More text and the image link', static function () {
    $GLOBALS['__thumbnail_id'] = 4;

    try {
        $card = elemacy_card([['part' => 'image'], ['part' => 'title'], ['part' => 'button']], ['post_title' => ''], [PostCardDefinition::WHOLE_CARD_LINK => 'yes']);
        $linked_image = elemacy_card([['part' => 'image'], ['part' => 'button']], ['post_title' => '']);
    } finally {
        unset($GLOBALS['__thumbnail_id']);
    }

    return elemacy_has($card, '<a class="elemacy-post-card__overlay" href="https://example.test/?p=11"><span class="elemacy-post-card__sr">(no title)</span></a>')
        && elemacy_has($card, '<span class="elemacy-post-card__sr">: (no title)</span>')
        && elemacy_has($linked_image, 'aria-label="(no title)"');
});

check('the whole-card overlay also makes an image-only card clickable, and the card needs no Title for it', static function () {
    $GLOBALS['__thumbnail_id'] = 4;

    try {
        $html = elemacy_card([['part' => 'image']], [], [PostCardDefinition::WHOLE_CARD_LINK => 'yes']);
    } finally {
        unset($GLOBALS['__thumbnail_id']);
    }

    return elemacy_has($html, 'class="elemacy-post-card__overlay"')
        && !elemacy_has($html, '<a href=')
        && elemacy_has($html, '<span class="elemacy-post-card__sr">A title</span>');
});

check('without the whole-card link there is no overlay anchor', static function () {
    return !elemacy_has(elemacy_card([['part' => 'title'], ['part' => 'button']]), 'elemacy-post-card__overlay');
});

check('the stylesheet gives the overlay its own click area and no longer stretches the Title link', static function () {
    $css = (string) file_get_contents(ELEMACY_PATH . 'src/Modules/LoopBuilder/assets/css/post-card.css');

    return (bool) preg_match('/\.elemacy-post-card__overlay\s*\{[^}]*position:\s*absolute;[^}]*inset:\s*0;/', $css)
        && !elemacy_has($css, '__link::after');
});

check('the Terms separator is decorative, as the Meta separator is', static function () {
    $GLOBALS['__post_terms'] = [new WP_Term(1, 'category', ['name' => 'News']), new WP_Term(2, 'category', ['name' => 'Tips'])];

    try {
        $html = elemacy_card([['part' => 'terms', 'terms_taxonomy' => 'category', 'terms_separator' => '|']]);
    } finally {
        unset($GLOBALS['__post_terms']);
    }

    return elemacy_has($html, '<span class="elemacy-post-card__term">News</span><span class="elemacy-post-card__separator" aria-hidden="true">|</span><span class="elemacy-post-card__term">Tips</span>');
});
