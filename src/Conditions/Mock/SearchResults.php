<?php

namespace Elemacy\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Conditions\MockCondition;

class SearchResults extends MockCondition
{
    public function get_name(): string
    {
        return 'general/search_results';
    }

    public function get_type(): string
    {
        return 'general';
    }

    public function get_label(): string
    {
        return __('Search Results', 'elemacy');
    }
}
