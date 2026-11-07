<?php

defined('ABSPATH') || exit;

use Elemacy\Modules\FormBuilder\Fields\Acceptance;
use Elemacy\Modules\FormBuilder\Fields\Checkbox;
use Elemacy\Modules\FormBuilder\Fields\Date;
use Elemacy\Modules\FormBuilder\Fields\Email;
use Elemacy\Modules\FormBuilder\Fields\Hidden;
use Elemacy\Modules\FormBuilder\Fields\Html;
use Elemacy\Modules\FormBuilder\Fields\Number;
use Elemacy\Modules\FormBuilder\Fields\Radio;
use Elemacy\Modules\FormBuilder\Fields\Select;
use Elemacy\Modules\FormBuilder\Fields\Tel;
use Elemacy\Modules\FormBuilder\Fields\Text;
use Elemacy\Modules\FormBuilder\Fields\Textarea;
use Elemacy\Modules\FormBuilder\Fields\Time;
use Elemacy\Modules\FormBuilder\Fields\Url;

/**
 * The form field types free ships, as class-strings (built through the
 * container) or ready-made instances, in the order the editor lists them.
 * Add one line per type; add-ons use Hooks::FORM_FIELD_TYPES_REGISTER_ACTION.
 *
 * @see \Elemacy\Modules\FormBuilder\Services\FieldTypeRegistry
 */
return [
    Text::class,
    Email::class,
    Textarea::class,
    Url::class,
    Tel::class,
    Number::class,
    Date::class,
    Time::class,
    Select::class,
    Radio::class,
    Checkbox::class,
    Acceptance::class,
    Hidden::class,
    Html::class,
];
