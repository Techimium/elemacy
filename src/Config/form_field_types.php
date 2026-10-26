<?php

defined('ABSPATH') || exit;

use Elemacy\Forms\Fields\Acceptance;
use Elemacy\Forms\Fields\Checkbox;
use Elemacy\Forms\Fields\Date;
use Elemacy\Forms\Fields\Email;
use Elemacy\Forms\Fields\Hidden;
use Elemacy\Forms\Fields\Html;
use Elemacy\Forms\Fields\Number;
use Elemacy\Forms\Fields\Radio;
use Elemacy\Forms\Fields\Select;
use Elemacy\Forms\Fields\Tel;
use Elemacy\Forms\Fields\Text;
use Elemacy\Forms\Fields\Textarea;
use Elemacy\Forms\Fields\Time;
use Elemacy\Forms\Fields\Url;

/**
 * The form field types free ships, as class-strings (built through the
 * container) or ready-made instances, in the order the editor lists them.
 * Add one line per type; add-ons use Hooks::FORM_FIELD_TYPES_REGISTER_ACTION.
 *
 * @see \Elemacy\Forms\FieldTypeRegistry
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
