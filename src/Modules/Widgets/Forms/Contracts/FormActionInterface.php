<?php

namespace Elemacy\Modules\Widgets\Forms\Contracts;

defined('ABSPATH') || exit;

interface FormActionInterface
{
    public function key(): string;
    public function execute(array $settings, array $values): bool;
}
