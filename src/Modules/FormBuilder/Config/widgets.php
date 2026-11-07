<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\FormBuilder\Widgets\Form\Form;

return [
    [
        'name' => 'elemacy-form',
        'title' => __('Form Builder', 'elemacy'),
        'icon' => 'rows-3',
        'class' => Form::class,
    ],
];
