<?php

namespace Elemacy\Core\Constants;

use Elemacy\Core\Validation\Rules\ArrayRule;
use Elemacy\Core\Validation\Rules\BooleanRule;
use Elemacy\Core\Validation\Rules\DateFormatRule;
use Elemacy\Core\Validation\Rules\DateRule;
use Elemacy\Core\Validation\Rules\DateTimeRule;
use Elemacy\Core\Validation\Rules\EmailRule;
use Elemacy\Core\Validation\Rules\EmailUniqueRule;
use Elemacy\Core\Validation\Rules\FloatRule;
use Elemacy\Core\Validation\Rules\GreaterThanEqualRule;
use Elemacy\Core\Validation\Rules\GreaterThanRule;
use Elemacy\Core\Validation\Rules\InRule;
use Elemacy\Core\Validation\Rules\IntegerRule;
use Elemacy\Core\Validation\Rules\IsValidImageIdRule;
use Elemacy\Core\Validation\Rules\LessThanEqualRule;
use Elemacy\Core\Validation\Rules\LessThanRule;
use Elemacy\Core\Validation\Rules\MaxRule;
use Elemacy\Core\Validation\Rules\MinRule;
use Elemacy\Core\Validation\Rules\NotInRule;
use Elemacy\Core\Validation\Rules\NullableRule;
use Elemacy\Core\Validation\Rules\NumberRule;
use Elemacy\Core\Validation\Rules\ObjectRule;
use Elemacy\Core\Validation\Rules\ProhibitedIfRule;
use Elemacy\Core\Validation\Rules\RegexRule;
use Elemacy\Core\Validation\Rules\RequiredIfExists;
use Elemacy\Core\Validation\Rules\RequiredIfRule;
use Elemacy\Core\Validation\Rules\RequiredRule;
use Elemacy\Core\Validation\Rules\SameAsRule;
use Elemacy\Core\Validation\Rules\Sanitizer;
use Elemacy\Core\Validation\Rules\StringRule;
use Elemacy\Core\Validation\Rules\UrlRule;
use Elemacy\Core\Validation\Rules\UserExists;

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
