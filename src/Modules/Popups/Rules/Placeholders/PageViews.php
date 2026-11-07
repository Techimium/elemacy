<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class PageViews extends PlaceholderRule
{
    public function get_name(): string
    {
        return 'page_views';
    }

    public function get_label(): string
    {
        return __('After X page views', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'count',
                'label'   => __('Page views', 'elemacy'),
                'type'    => 'number',
                'default' => 3,
                'min'     => 1,
            ],
        ];
    }
}
