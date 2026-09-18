<?php

namespace Elemacy\Core\Constants;

defined('ABSPATH') || exit;

use Elemacy\Http\Validation\Rules\ArrayRule;
use Elemacy\Http\Validation\Rules\BooleanRule;
use Elemacy\Http\Validation\Rules\DateFormatRule;
use Elemacy\Http\Validation\Rules\DateRule;
use Elemacy\Http\Validation\Rules\DateTimeRule;
use Elemacy\Http\Validation\Rules\EmailRule;
use Elemacy\Http\Validation\Rules\EmailUniqueRule;
use Elemacy\Http\Validation\Rules\FloatRule;
use Elemacy\Http\Validation\Rules\GreaterThanEqualRule;
use Elemacy\Http\Validation\Rules\GreaterThanRule;
use Elemacy\Http\Validation\Rules\InRule;
use Elemacy\Http\Validation\Rules\IntegerRule;
use Elemacy\Http\Validation\Rules\IsValidImageIdRule;
use Elemacy\Http\Validation\Rules\LessThanEqualRule;
use Elemacy\Http\Validation\Rules\LessThanRule;
use Elemacy\Http\Validation\Rules\MaxRule;
use Elemacy\Http\Validation\Rules\MinRule;
use Elemacy\Http\Validation\Rules\NotInRule;
use Elemacy\Http\Validation\Rules\NullableRule;
use Elemacy\Http\Validation\Rules\NumberRule;
use Elemacy\Http\Validation\Rules\ObjectRule;
use Elemacy\Http\Validation\Rules\ProhibitedIfRule;
use Elemacy\Http\Validation\Rules\RegexRule;
use Elemacy\Http\Validation\Rules\RequiredIfExists;
use Elemacy\Http\Validation\Rules\RequiredIfRule;
use Elemacy\Http\Validation\Rules\RequiredRule;
use Elemacy\Http\Validation\Rules\SameAsRule;
use Elemacy\Http\Validation\Rules\Sanitizer;
use Elemacy\Http\Validation\Rules\StringRule;
use Elemacy\Http\Validation\Rules\UrlRule;
use Elemacy\Http\Validation\Rules\UserExists;

class Validation
{
    /**
     * The rules to validator method map
     * 
     * @var array
     * 
     * @since 3.3.0
     */
    const RULE_MAP = [
        'required'  => RequiredRule::class,
        'string'    => StringRule::class,
        'array'     => ArrayRule::class,
        'object'    => ObjectRule::class,
        'boolean'   => BooleanRule::class,
        'integer'   => IntegerRule::class,
        'number'    => NumberRule::class,
        'float'     => FloatRule::class,
        'email'     => EmailRule::class,
        'email_unique' => EmailUniqueRule::class,
        'url'       => UrlRule::class,
        'min'       => MinRule::class,
        'max'       => MaxRule::class,
        'in'        => InRule::class,
        'not_in'    => NotInRule::class,
        'regex'     => RegexRule::class,
        'sanitize'  => Sanitizer::class,
        'same_as'   => SameAsRule::class,
        'nullable'  => NullableRule::class,
        'date'      => DateRule::class,
        'datetime'  => DateTimeRule::class,
        'date_format' => DateFormatRule::class,
        'is_valid_image_id' => IsValidImageIdRule::class,
        'required_if' => RequiredIfRule::class,
        'prohibited_if' => ProhibitedIfRule::class,
        'required_if_exists' => RequiredIfExists::class,
        'user_exists' => UserExists::class,
        'gt' => GreaterThanRule::class,
        'gte' => GreaterThanEqualRule::class,
        'lt' => LessThanRule::class,
        'lte' => LessThanEqualRule::class,
    ];
}
