<?php

namespace Elemacy\Loop\Sources\RelatedPosts;

defined('ABSPATH') || exit;

use Elemacy\Loop\Access\PublicLoopLimits;
use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Placement\HostFacts;
use Elemacy\Loop\Sources\Posts\PostsSettings;

/**
 * The Related Posts source's public-context policy: the current post comes
 * only from verified host facts, never from the request.
 *
 * @internal
 */
class RelatedPostsPolicy implements PublicLoopSourcePolicyInterface
{
    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        PublicLoopLimits::assert_page($page);
        PublicLoopLimits::assert_scalars($settings, array_keys(RelatedPostsSettings::defaults()));
        $saved = $settings + RelatedPostsSettings::defaults();

        $taxonomies = $saved[RelatedPostsSettings::TAXONOMIES] ?? [];
        if ('' === $taxonomies) {
            $taxonomies = [];
        }
        if (!is_array($taxonomies)) {
            throw PublicLoopLimits::invalid_settings();
        }

        $order_by = (string) $saved[RelatedPostsSettings::ORDER_BY];
        $order = strtoupper((string) $saved[RelatedPostsSettings::ORDER]);

        return [
            RelatedPostsSettings::TAXONOMIES => array_values(array_filter(array_map('sanitize_key', array_filter($taxonomies, 'is_string')))),
            RelatedPostsSettings::PER_PAGE => PostsSettings::page_size($saved[RelatedPostsSettings::PER_PAGE]),
            RelatedPostsSettings::ORDER_BY => in_array($order_by, RelatedPostsSettings::ORDER_BY_CHOICES, true) ? $order_by : RelatedPostsSettings::DEFAULT_ORDER_BY,
            RelatedPostsSettings::ORDER => in_array($order, ['ASC', 'DESC'], true) ? $order : RelatedPostsSettings::DEFAULT_ORDER,
            RelatedPostsSettings::FALLBACK => 'yes' === $saved[RelatedPostsSettings::FALLBACK] ? 'yes' : '',
            RelatedPostsSettings::CURRENT_POST_ID => (int) ($context[HostFacts::POST_ID] ?? 0),
            RelatedPostsSettings::PUBLIC_QUERY => true,
            'paged' => $page,
        ];
    }
}
