<?php

namespace Elemacy\Forms\Fields;

use Elemacy\Forms\Definition\Field;
use Elemacy\Forms\Rendering\RenderContext;

defined('ABSPATH') || exit;

/**
 * Several options, any number ticked. Required means at least one: no box
 * carries the required attribute (the browser would demand every one); the
 * form runtime checks the group instead.
 */
final class Checkbox extends AbstractChoiceFieldType
{
    public function key(): string
    {
        return 'checkbox';
    }

    public function label(): string
    {
        return __('Checkbox', 'elemacy');
    }

    public function render_control(Field $field, RenderContext $context): string
    {
        return Radio::options_markup($field, $context, 'checkbox', true);
    }

    protected function is_multiple(): bool
    {
        return true;
    }
}
