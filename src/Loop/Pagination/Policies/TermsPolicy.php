<?php

namespace Elemacy\Loop\Pagination\Policies;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\DataSources\TermsQuery;
use Elemacy\Modules\Widgets\Support\PublicLoopLimits;

/**
 * The Terms source's public-context policy (design.md D7) — extracted from
 * Modules\Widgets\DataSources\TaxonomyDataSource so it can be exercised
 * without an Elementor widget/document instance. Behavior is unchanged from
 * before the move; TaxonomyDataSource itself now delegates
 * get_public_settings() to an instance of this class. Depends on TermsQuery
 * directly (constructor injection, defaulting to a fresh instance), the same
 * "validate then run through the query's own sanitize_ajax_settings()"
 * pattern PostsPolicy already established.
 */
class TermsPolicy implements PublicLoopSourcePolicyInterface
{
    protected TermsQuery $query;

    public function __construct(?TermsQuery $query = null)
    {
        $this->query = $query ?? new TermsQuery();
    }

    /** {@inheritDoc} */
    public function get_public_settings(array $settings, array $context, int $page): array
    {
        PublicLoopLimits::assert_page($page);
        PublicLoopLimits::assert_scalars($settings, ['taxonomy', 'terms_number', 'terms_orderby', 'terms_order', 'hide_empty', 'exclude_current_term']);
        $saved = $settings + [
            'taxonomy' => 'category',
            'terms_number' => 6,
            'hide_empty' => 'yes',
            'exclude_current_term' => 'yes',
        ];
        $saved['terms_number'] = PublicLoopLimits::page_size($saved['terms_number']);
        $saved['current_term_id'] = (int) ($context['term_id'] ?? 0);
        return $this->query->sanitize_ajax_settings($saved, $page);
    }
}
