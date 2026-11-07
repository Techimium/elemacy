<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\FormBuilder\Widgets\Form\Form;

return [
    [
        'name' => 'elemacy-form',
        'title' => __('Form Builder', 'elemacy'),
        'icon' => 'rows-3',
        'description' => __('Build forms that validate input, send email notifications and work inside popups.', 'elemacy'),
        'class' => Form::class,
    ],
];
