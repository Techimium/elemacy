<?php

namespace Elemacy\Loop\Sources\Terms;

defined('ABSPATH') || exit;

use Elemacy\Loop\Contracts\PublicLoopSourcePolicyInterface;
use Elemacy\Loop\Placement\HostFacts;
use Elemacy\Loop\Access\PublicLoopLimits;

/**
 * The Terms source's public-context policy, kept free of any Elementor
 * widget/document dependency; TaxonomyDataSource delegates get_public_settings()
 * to an instance of this class.
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
        PublicLoopLimits::assert_scalars($settings, array_keys(TermsSettings::defaults()));
        $saved = $settings + TermsSettings::defaults();
        $saved[TermsSettings::NUMBER] = PublicLoopLimits::page_size($saved[TermsSettings::NUMBER]);
        $saved[TermsSettings::CURRENT_TERM_ID] = (int) ($context[HostFacts::TERM_ID] ?? 0);
        return $this->query->sanitize_ajax_settings($saved, $page);
    }
}
