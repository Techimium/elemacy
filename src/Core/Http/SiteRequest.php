<?php

namespace Elemacy\Core\Http;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\Request;
use Elemacy\Core\Sanitizer;

class SiteRequest implements Request
{
    protected $attributes = [];
    protected $headers = [];

    public function __get(string $name)
    {
        return $this->input($name);
    }

    public function __set(string $name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public static function instance()
    {
        return new static();
    }

    public function get_method()
    {
        return Sanitizer::apply_rule(wp_unslash($_SERVER['REQUEST_METHOD']), Sanitizer::TEXT); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
    }

    public function get_route()
    {
        return Sanitizer::apply_rule(wp_unslash($_SERVER['REQUEST_URI']), Sanitizer::TEXT); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
    }

    public function get_headers()
    {
        // getallheaders() is missing on some SAPIs (e.g. CLI); never fatal there.
        $this->headers = function_exists('getallheaders') ? getallheaders() : [];

        return $this->headers;
    }

    public function all()
    {
        return $this->attributes;
    }

    public function clean()
    {
        // Attributes are set internally; raw input is sanitized per-field in input().
        return $this->all();
    }

    public function except(array $attributes)
    {
        return array_diff_key($this->all(), array_flip($attributes));
    }

    public function only(string $key)
    {
        return $this->input($key);
    }

    public function input(string $key, $type = 'text', $default_value = null)
    {
        if (isset($this->attributes[$key])) {
            return Sanitizer::apply_rule($this->attributes[$key], $type);
        }

        $raw_value = $_POST[$key] ?? $_GET[$key] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash

        if (null === $raw_value) {
            return $default_value;
        }

        $unslashed = wp_unslash($raw_value);

        return Sanitizer::apply_rule($unslashed, $type);
    }

    public function has(string $key)
    {
        return $this->input($key) !== null;
    }

    public function get(string $key, string $type, $default_value = null)
    {
        $value = $this->only($key) ?? $default_value;

        $value = Sanitizer::apply_rule($value, $type);

        return $value;
    }

    /**
     * Get a string value with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_string(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::TEXT, $default_value);
    }

    /**
     * Get a date value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string
     */
    public function get_date(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::DATE, $default_value);
    }

    /**
     * Get a datetime value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string
     */
    public function get_datetime(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::DATETIME, $default_value);
    }

    /**
     * Get a text with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_text(string $key, $default_value = null)
    {
        return $this->get_string($key, $default_value);
    }

    /**
     * Get a html supported content with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_html(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::TEXTAREA, $default_value);
    }

    /**
     * Get a email with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_email(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::EMAIL, $default_value);
    }

    /**
     * Get a url with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_url(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::URL, $default_value);
    }

    /**
     * Get a key value with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_key(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::KEY, $default_value);
    }

    /**
     * Get a title value with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_title(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::TITLE, $default_value);
    }

    /**
     * Get a file name with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_file_name(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::TITLE, $default_value);
    }

    /**
     * Get mime type with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param string|null $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_mime_type(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::MIME_TYPE, $default_value);
    }

    /**
     * Get an integer value.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param int|null $default_value Default value if the key doesn't exist.
     * @return int|null
     */
    public function get_int(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::INT, $default_value);
    }

    /**
     * Get a boolean value.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param bool $default_value Default value if the key doesn't exist.
     * @return bool
     */
    public function get_bool(string $key, bool $default_value = false)
    {
        return $this->get($key, Sanitizer::BOOL, $default_value);
    }

    /**
     * Get a float value.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param float|null $default_value Default value if the key doesn't exist.
     * @return float|null
     */
    public function get_float(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::FLOAT, $default_value);
    }

    /**
     * Get a money value.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param float $default_value Default value if the key doesn't exist.
     * @return float
     */
    public function get_money(string $key, $default_value = 0)
    {
        return $this->get($key, Sanitizer::MONEY, $default_value);
    }

    /**
     * Get an array value.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param array|null $default_value Default value if the key doesn't exist.
     * @return array|null
     */
    public function get_array(string $key, $default_value = null)
    {
        return $this->get($key, Sanitizer::ARRAY , $default_value);
    }
}
