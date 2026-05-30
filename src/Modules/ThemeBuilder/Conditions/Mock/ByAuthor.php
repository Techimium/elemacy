<?php

namespace Elemacy\Modules\ThemeBuilder\Conditions\Mock;

defined('ABSPATH') || exit;

use Elemacy\Modules\ThemeBuilder\Conditions\MockCondition;

class ByAuthor extends MockCondition
{
    public function get_name(): string
    {
        return 'singular/by_author';
    }

    public function get_type(): string
    {
        return 'singular';
    }

    public function get_label(): string
    {
        return __('By Author', 'elemacy');
    }
}
