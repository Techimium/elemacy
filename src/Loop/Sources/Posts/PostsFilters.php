<?php

namespace Elemacy\Loop\Sources\Posts;

defined('ABSPATH') || exit;

use Elemacy\Http\Exceptions\ValidationException;
use Elemacy\Loop\Access\PublicLoopLimits;

/**
 * The Posts source's optional filters: validating their saved values for a
 * public request, and turning them into WP_Query arguments. Every filter is
 * off by default, so a query with none of them set is unchanged.
 *
 * @internal
 */
final class PostsFilters
{
    /** @var array<string, string> Date range choice => how far back it reaches. */
    public const DATE_RANGES = [
        'day' => '-1 day',
        'week' => '-1 week',
        'month' => '-1 month',
        'quarter' => '-3 months',
        'year' => '-1 year',
    ];
    public const DATE_RANGE_ANY = '';
    public const DATE_RANGE_CUSTOM = 'custom';

    public const COMPARISONS = ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'EXISTS', 'NOT EXISTS'];

    private const NUMERIC_COMPARISONS = ['>', '>=', '<', '<='];
    private const VALUELESS_COMPARISONS = ['EXISTS', 'NOT EXISTS'];
    private const MAX_IDS = 100;
    private const MAX_META_CONDITIONS = 10;

    /** @var string[] The saved settings holding lists of IDs. */
    private const ID_LISTS = [
        PostsSettings::INCLUDE_TERMS,
        PostsSettings::INCLUDE_AUTHORS,
        PostsSettings::EXCLUDE_POSTS,
        PostsSettings::EXCLUDE_TERMS,
        PostsSettings::EXCLUDE_AUTHORS,
    ];

    /**
     * The filter settings a public request may use, from saved settings only.
     *
     * @param array $raw Saved settings.
     * @return array
     * @throws ValidationException On a saved value of the wrong shape.
     */
    public static function sanitize(array $raw): array
    {
        $clean = [];

        foreach (self::ID_LISTS as $key) {
            $clean[$key] = self::id_list($raw[$key] ?? []);
        }

        $range = (string) ($raw[PostsSettings::DATE_RANGE] ?? self::DATE_RANGE_ANY);
        $clean[PostsSettings::DATE_RANGE] = self::known_range($range) ? $range : self::DATE_RANGE_ANY;
        $clean[PostsSettings::DATE_AFTER] = self::date_value($raw[PostsSettings::DATE_AFTER] ?? '');
        $clean[PostsSettings::DATE_BEFORE] = self::date_value($raw[PostsSettings::DATE_BEFORE] ?? '');
        $clean[PostsSettings::META_CONDITIONS] = self::meta_conditions($raw[PostsSettings::META_CONDITIONS] ?? []);
        $clean[PostsSettings::ORDER_META_KEY] = self::meta_key($raw[PostsSettings::ORDER_META_KEY] ?? '');
        $clean[PostsSettings::IGNORE_STICKY] = 'yes' === ($raw[PostsSettings::IGNORE_STICKY] ?? '') ? 'yes' : '';
        $clean[PostsSettings::QUERY_ID] = sanitize_key((string) ($raw[PostsSettings::QUERY_ID] ?? ''));

        // A later page cannot know what the page's earlier lists showed, so
        // only a list without pagination can honour this.
        $clean[PostsSettings::AVOID_DUPLICATES] = self::avoids_duplicates($raw) ? 'yes' : '';

        return $clean;
    }

    /**
     * Whether this list skips posts already shown. A public query's own
     * settings already say so: sanitize() kept the switch only for a list
     * without pagination, and the query's pagination_type is not the saved one.
     */
    public static function avoids_duplicates(array $settings): bool
    {
        return 'yes' === ($settings[PostsSettings::AVOID_DUPLICATES] ?? '')
            && (!empty($settings[PostsSettings::PUBLIC_QUERY]) || empty($settings['pagination_type']));
    }

    /**
     * Adds the filters' arguments to a query.
     *
     * @param array $args     WP_Query arguments built so far.
     * @param array $settings Sanitized public settings, or the editor's own.
     * @return array
     */
    public static function apply(array $args, array $settings): array
    {
        $args = self::apply_terms($args, $settings);
        $args = self::apply_ids($args, $settings);
        $args = self::apply_date($args, $settings);
        $args = self::apply_meta($args, $settings);
        $args = self::apply_order($args, $settings);

        if ('yes' === ($settings[PostsSettings::IGNORE_STICKY] ?? '')) {
            $args['ignore_sticky_posts'] = true;
        }

        return $args;
    }

    private static function apply_terms(array $args, array $settings): array
    {
        $taxonomies = get_object_taxonomies((string) $args['post_type']);
        $tax_query = array_merge(
            self::term_clauses($settings[PostsSettings::INCLUDE_TERMS] ?? [], $taxonomies, 'IN'),
            self::term_clauses($settings[PostsSettings::EXCLUDE_TERMS] ?? [], $taxonomies, 'NOT IN')
        );

        if ($tax_query) {
            $args['tax_query'] = array_merge(['relation' => 'AND'], $tax_query); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- An explicit authored filter.
        }

        return $args;
    }

    /**
     * One clause per taxonomy, so a post must match each chosen taxonomy and
     * at least one term within it. A term whose taxonomy this post type does
     * not have is dropped.
     *
     * @param mixed    $ids        Saved term IDs.
     * @param string[] $taxonomies The queried post type's taxonomies.
     * @param string   $operator   IN or NOT IN.
     * @return array
     */
    private static function term_clauses($ids, array $taxonomies, string $operator): array
    {
        $by_taxonomy = [];

        foreach (self::id_list_or_empty($ids) as $id) {
            $term = get_term($id);

            if ($term instanceof \WP_Term && in_array($term->taxonomy, $taxonomies, true)) {
                $by_taxonomy[$term->taxonomy][] = $id;
            }
        }

        $clauses = [];

        foreach ($by_taxonomy as $taxonomy => $term_ids) {
            $clauses[] = [
                'taxonomy' => $taxonomy,
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => $operator,
            ];
        }

        return $clauses;
    }

    private static function apply_ids(array $args, array $settings): array
    {
        $include_authors = self::id_list_or_empty($settings[PostsSettings::INCLUDE_AUTHORS] ?? []);
        $exclude_authors = self::id_list_or_empty($settings[PostsSettings::EXCLUDE_AUTHORS] ?? []);
        $exclude_posts = self::id_list_or_empty($settings[PostsSettings::EXCLUDE_POSTS] ?? []);

        if ($include_authors) {
            $args['author__in'] = $include_authors;
        }
        if ($exclude_authors) {
            $args['author__not_in'] = $exclude_authors;
        }
        if ($exclude_posts) {
            $args['post__not_in'] = array_merge($args['post__not_in'] ?? [], $exclude_posts); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- An explicit authored filter.
        }

        return $args;
    }

    private static function apply_date(array $args, array $settings): array
    {
        $range = (string) ($settings[PostsSettings::DATE_RANGE] ?? self::DATE_RANGE_ANY);

        if (isset(self::DATE_RANGES[$range])) {
            $args['date_query'] = [
                [
                    'after' => self::DATE_RANGES[$range],
                    'inclusive' => true,
                ],
            ];

            return $args;
        }

        if (self::DATE_RANGE_CUSTOM === $range) {
            $window = array_filter([
                'after' => self::date_value($settings[PostsSettings::DATE_AFTER] ?? ''),
                'before' => self::date_value($settings[PostsSettings::DATE_BEFORE] ?? ''),
            ]);

            if ($window) {
                $args['date_query'] = [$window + ['inclusive' => true]];
            }
        }

        return $args;
    }

    private static function apply_meta(array $args, array $settings): array
    {
        $clauses = [];

        foreach (self::meta_conditions_or_empty($settings[PostsSettings::META_CONDITIONS] ?? []) as $condition) {
            $clause = [
                'key' => $condition['key'],
                'compare' => $condition['compare'],
            ];

            if (!in_array($condition['compare'], self::VALUELESS_COMPARISONS, true)) {
                $clause['value'] = $condition['value'];
            }
            if (in_array($condition['compare'], self::NUMERIC_COMPARISONS, true) && is_numeric($condition['value'])) {
                $clause['type'] = 'NUMERIC';
            }

            $clauses[] = $clause;
        }

        if ($clauses) {
            $args['meta_query'] = array_merge(['relation' => 'AND'], $clauses); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- An explicit authored filter.
        }

        return $args;
    }

    private static function apply_order(array $args, array $settings): array
    {
        $order_by = $args['orderby'] ?? '';

        if ('id' === $order_by) {
            $args['orderby'] = 'ID';
        }

        if (in_array($order_by, PostsSettings::META_ORDERS, true)) {
            $key = self::meta_key($settings[PostsSettings::ORDER_META_KEY] ?? '');

            if ('' === $key) {
                $args['orderby'] = PostsSettings::DEFAULT_ORDER_BY;
            } else {
                $args['meta_key'] = $key; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- An explicit authored sort.
            }
        }

        return $args;
    }

    /**
     * @param mixed $value Saved list.
     * @return int[]
     * @throws ValidationException On a value that is not a list of IDs.
     */
    public static function id_list($value): array
    {
        if ('' === $value || null === $value) {
            return [];
        }
        if (!is_array($value)) {
            throw PublicLoopLimits::invalid_settings();
        }

        $ids = [];

        foreach ($value as $id) {
            if (!is_scalar($id) || !is_numeric($id) || (int) $id <= 0) {
                throw PublicLoopLimits::invalid_settings();
            }
            $ids[] = (int) $id;
        }

        return array_slice(array_values(array_unique($ids)), 0, self::MAX_IDS);
    }

    /**
     * Reads a list that is already sanitized, or the editor's own settings,
     * where a malformed value simply filters nothing.
     *
     * @param mixed $value
     * @return int[]
     */
    private static function id_list_or_empty($value): array
    {
        try {
            return self::id_list($value);
        } catch (ValidationException $error) {
            return [];
        }
    }

    private static function known_range(string $range): bool
    {
        return self::DATE_RANGE_CUSTOM === $range || isset(self::DATE_RANGES[$range]);
    }

    /**
     * @param mixed $value Saved date.
     * @return string The date, or empty when it is not one.
     */
    private static function date_value($value): string
    {
        if (!is_string($value) || !preg_match('/\A\d{4}-\d{2}-\d{2}(?: \d{2}:\d{2}(?::\d{2})?)?\z/', $value)) {
            return '';
        }

        return false === strtotime($value) ? '' : $value;
    }

    /**
     * @param mixed $value Saved repeater rows.
     * @return array<int, array{key: string, compare: string, value: string}>
     * @throws ValidationException On a value that is not a list of rows.
     */
    private static function meta_conditions($value): array
    {
        if ('' === $value || null === $value) {
            return [];
        }
        if (!is_array($value)) {
            throw PublicLoopLimits::invalid_settings();
        }

        $conditions = [];

        foreach ($value as $row) {
            if (!is_array($row)) {
                throw PublicLoopLimits::invalid_settings();
            }

            $key = self::meta_key($row['key'] ?? '');
            $compare = (string) ($row['compare'] ?? '=');
            $saved = $row['value'] ?? '';

            if ('' !== $key && in_array($compare, self::COMPARISONS, true) && is_scalar($saved)) {
                $conditions[] = [
                    'key' => $key,
                    'compare' => $compare,
                    'value' => (string) $saved,
                ];
            }
        }

        return array_slice($conditions, 0, self::MAX_META_CONDITIONS);
    }

    /**
     * @param mixed $value
     * @return array<int, array{key: string, compare: string, value: string}>
     */
    private static function meta_conditions_or_empty($value): array
    {
        try {
            return self::meta_conditions($value);
        } catch (ValidationException $error) {
            return [];
        }
    }

    /**
     * @param mixed $value Saved field name.
     * @return string The name, or empty for an unusable or protected key.
     */
    private static function meta_key($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $key = sanitize_text_field($value);

        return '' === $key || is_protected_meta($key, 'post') ? '' : $key;
    }
}
