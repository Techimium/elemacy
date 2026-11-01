<?php

namespace Elemacy\Modules\FormBuilder\Fields;

defined('ABSPATH') || exit;

/**
 * @internal
 */
final class Text extends AbstractFieldType
{
    public function key(): string
    {
        return 'text';
    }

    public function label(): string
    {
        return __('Text', 'elemacy');
    }
}
