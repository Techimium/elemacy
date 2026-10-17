<?php

namespace Elemacy\Modules\ThemeBuilder\Services;

defined('ABSPATH') || exit;

use Elemacy\Conditions\Support\Taxonomies;
use WP_Term;

/**
 * The WP_Query args an Archive template's editor preview renders against, built
 * from the document's Preview Settings so that WordPress treats the preview as
 * a real archive of the chosen kind (a category, a tag, a taxonomy term, an
 * author, a month, or just a post type). Whatever the settings hold, a choice
 * that no longer resolves falls back to the post type archive, so the editor
 * never has to show an error.
 */
class ArchivePreviewQuery
{
    public const TYPE_POST_TYPE = 'post_type';
    public const TYPE_TERM      = 'term';
    public const TYPE_AUTHOR    = 'author';
    public const TYPE_MONTH     = 'month';

    /**
     * @param array{type?: mixed, post_type?: mixed, term?: mixed, author?: mixed, month?: mixed} $settings
     *
     * @return array<string, mixed>
     */
    public function build(array $settings): array
    {
        $base = $this->post_type_args((string) ($settings['post_type'] ?? ''));

        switch ((string) ($settings['type'] ?? self::TYPE_POST_TYPE)) {
            case self::TYPE_TERM:
                return $this->term_args($base, (int) ($settings['term'] ?? 0));

            case self::TYPE_AUTHOR:
                return $this->author_args($base, (int) ($settings['author'] ?? 0));

            case self::TYPE_MONTH:
                return $this->month_args($base, (string) ($settings['month'] ?? ''));
        }

        return $base;
    }

    /**
     * The post type archive: also what every other type falls back to.
     *
     * @return array<string, mixed>
     */
    protected function post_type_args(string $post_type): array
    {
        if ('' === $post_type || !post_type_exists($post_type)) {
            $post_type = 'post';
        }

        return [
            'post_type'           => $post_type,
            'posts_per_page'      => (int) get_option('posts_per_page'),
            'ignore_sticky_posts' => true,
        ];
    }

    /**
     * Categories and tags use their own query vars: WordPress only resolves
     * the archive's title and queried term from those, not from a tax_query.
     * Any other public taxonomy's term uses a tax_query and lists the post
     * types that taxonomy belongs to, as its real archive does.
     *
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    protected function term_args(array $base, int $term_id): array
    {
        $term = $term_id > 0 ? get_term($term_id) : null;

        if (!$term instanceof WP_Term || !$this->is_public_taxonomy($term->taxonomy)) {
            return $base;
        }

        if ('category' === $term->taxonomy) {
            return $base + ['cat' => $term_id];
        }

        if ('post_tag' === $term->taxonomy) {
            return $base + ['tag_id' => $term_id];
        }

        $taxonomy = get_taxonomy($term->taxonomy);

        return array_merge($base, [
            'post_type' => $taxonomy && !empty($taxonomy->object_type) ? array_values($taxonomy->object_type) : $base['post_type'],
            'tax_query' => [
                [
                    'taxonomy' => $term->taxonomy,
                    'field'    => 'term_id',
                    'terms'    => [$term_id],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    protected function author_args(array $base, int $user_id): array
    {
        if ($user_id <= 0 || !get_userdata($user_id)) {
            return $base;
        }

        return $base + ['author' => $user_id];
    }

    /**
     * Reads the year and month of a date picker value ("2024-03-15",
     * "2024-03-15 10:30", "2024-03").
     *
     * @param array<string, mixed> $base
     *
     * @return array<string, mixed>
     */
    protected function month_args(array $base, string $value): array
    {
        if (!preg_match('/^(\d{4})-(\d{2})(?:\D|$)/', trim($value), $match)) {
            return $base;
        }

        $year  = (int) $match[1];
        $month = (int) $match[2];

        if ($year < 1 || $month < 1 || $month > 12) {
            return $base;
        }

        return $base + [
            'year'     => $year,
            'monthnum' => $month,
        ];
    }

    protected function is_public_taxonomy(string $taxonomy): bool
    {
        foreach (Taxonomies::public_objects() as $public) {
            if ($public->name === $taxonomy) {
                return true;
            }
        }

        return false;
    }
}
