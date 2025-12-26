<?php

namespace Elemacy\Supports;

use Elemacy\Core\Sanitizer;
/**
 * A utility class for string manipulation and formatting.
 *
 * Provides various static methods to handle common string operations
 * such as type conversion, slug generation, and string incrementation.
 */
class Str
{
    /**
     * The increment styles.
     *
     * @var array
     */
    protected static $increment_styles = [
        'dash' => [
            '/-(\d+)$/',
            '-%d'
        ],
        'regular' => [
            ['/\((\d+)\)$/', '/\(\d+\)$/'],
            [' (%d)', '(%d)']
        ]
    ];

    /**
     * Convert a numeric string to a corresponding number.
     *
     * @param string $value
     * @return string|int|float
     */
    public static function to_number($value)
    {
        if (empty($value) || !is_numeric($value)) {
            return $value;
        }

        return strpos($value, '.') !== false || stripos($value, 'e') !== false
            ? (float) $value
            : (int) $value;
    }

    /**
     * Sanitize a string to use as a slug.
     *
     * This function sanitizes the input string according to the Sanitizer::TITLE rule,
     * which is used to sanitize title strings.
     *
     * @param string $value The string to sanitize.
     * @return string The sanitized string.
     */
    public static function slug($value)
    {
        return Sanitizer::apply_rule($value, Sanitizer::TITLE);
    }

    /**
     * Increment the string.
     *
     * @param string $value The string to increment.
     * @param string $style The style of the increment. Available styles are 'dash' and 'regular'.
     *
     * @return string The incremented string.
     * 
     * @since 1.0.0
     */
    public static function increment($value, $style = 'dash')
    {
        $style = static::$increment_styles[$style] ?? static::$increment_styles['regular'];

        if (is_array($style[0])) {
            $search_regex = reset($style[0]);
            $replace_regex = end($style[0]);
        } else {
            $search_regex = $replace_regex = $style[0];
        }

        if (is_array($style[1])) {
            $old_format = reset($style[1]);
            $new_format = end($style[1]);
        } else {
            $old_format = $new_format = $style[1];
        }

        if (preg_match($search_regex, $value, $matches)) {
            $count = intval($matches[1]) + 1;
            return preg_replace($replace_regex, sprintf($new_format, $count), $value, 1);
        } else {
            $count = 2;
            return $value . sprintf($old_format, $count);
        }
    }

    public static function trim($value, $charlist = null)
    {
        if (is_null($charlist)) {
            $trim_default_chars = " \n\r\t\v\0";
            $regex = '~^[\s\x{FEFF}\x{200B}\x{200E}' . $trim_default_chars . ']+|[\s\x{FEFF}\x{200B}\x{200E}' . $trim_default_chars . ']+$~u';

            return preg_replace($regex, '', $value) ?? trim($value);
        }

        return trim($value, $charlist);
    }

    public static function ltrim($value, $charlist = null)
    {
        if (is_null($charlist)) {
            $ltrim_default_chars = " \n\r\t\v\0";
            $regex = '~^[\s\x{FEFF}\x{200B}\x{200E}' . $ltrim_default_chars . ']+~u';

            return preg_replace($regex, '', $value) ?? ltrim($value);
        }

        return ltrim($value, $charlist);
    }

    public static function rtrim($value, $charlist = null)
    {
        if (is_null($charlist)) {
            $rtrim_default_chars = " \n\r\t\v\0";
            $regex = '~[\s\x{FEFF}\x{200B}\x{200E}' . $rtrim_default_chars . ']+$~u';

            return preg_replace($regex, '', $value) ?? rtrim($value);
        }

        return rtrim($value, $charlist);
    }

    public static function squish($value)
    {
        return preg_replace('~(\s|\x{3164}|\x{1160})+~u', ' ', static::trim($value));
    }

    public static function substr($value, $start, $length = null, $encoding = 'UTF-8')
    {
        return mb_substr($value, $start, $length, $encoding);
    }

    public static function take($value, int $limit)
    {
        if ($limit < 0) {
            return static::substr($value, $limit);
        }

        return static::substr($value, 0, $limit);
    }

    public static function to_base64($value)
    {
        return base64_encode($value);
    }
}