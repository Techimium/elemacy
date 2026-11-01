<?php

namespace Elemacy\Modules\Popups\Rules\Placeholders;

defined('ABSPATH') || exit;


/**
 * @internal
 */
class Roles extends PlaceholderRule
{
    public function get_name(): string
    {
        return 'roles';
    }

    public function get_label(): string
    {
        return __('By user role', 'elemacy');
    }

    public function get_config_schema(): array
    {
        return [
            [
                'key'     => 'roles',
                'label'   => __('User roles', 'elemacy'),
                'type'    => 'checkbox-group',
                'default' => [],
                'options' => [],
            ],
        ];
    }
}
