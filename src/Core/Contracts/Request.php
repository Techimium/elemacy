<?php

namespace Elemacy\Core\Contracts;

defined('ABSPATH') || exit;

/**
 * Contract for interacting with an HTTP request in a normalized way.
 *
 * This interface abstracts key methods for accessing request data, headers, routes, and HTTP method.
 *
 * @since 1.0.0
 */
interface Request
{
    /**
     * Get the HTTP method of the request (GET, POST, etc).
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_method();

    /**
     * Get the requested route or endpoint.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_route();

    /**
     * Get all HTTP headers from the request.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function get_headers();

    /**
     * Get all request input attributes.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function all();

    /**
     * Get all request input attributes with proper validation and sanitization.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function clean();

    /**
     * Get request data excluding specified attributes.
     *
     * @since 1.0.0
     *
     * @param array $attributes Keys to exclude.
     * @return array
     */
    public function except(array $attributes);

    /**
     * Get a single attribute by key.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @return mixed|null
     */
    public function only(string $key);

    /**
     * Alias of `only()`. Get input value by key.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @return mixed|null
     */
    public function input(string $key);

    /**
     * Get a value from the request with optional default and type casting.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string $type type to cast the result to: int, float, bool, string, array with proper sanitization.
     * @param mixed  $default_value Default value if the key doesn't exist.
     * @return mixed
     */
    public function get(string $key, string $type, $default_value = null);

    /** 
     * Check if the request has the provided key
     * 
     * @since 1.0.0
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key);

    /**
     * Get a string value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string
     */
    public function get_string(string $key, $default_value = null);

    /**
     * Get a date value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string
     */
    public function get_date(string $key, $default_value = null);

    /**
     * Get a datetime value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string
     */
    public function get_datetime(string $key, $default_value = null);

    /**
     * Get a text with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_text(string $key, $default_value = null);

    /**
     * Get a html supported content with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_html(string $key, $default_value = null);

    /**
     * Get a email with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_email(string $key, $default_value = null);

    /**
     * Get a url with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_url(string $key, $default_value = null);

    /**
     * Get a key value with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_key(string $key, $default_value = null);

    /**
     * Get a title value with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_title(string $key, $default_value = null);

    /**
     * Get a file name with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_file_name(string $key, $default_value = null);

    /**
     * Get mime type with sanitization applied.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param string|null  $default_value Default value if the key doesn't exist.
     * @return string|null
     */
    public function get_mime_type(string $key, $default_value = null);

    /**
     * Get an integer value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param int|null  $default_value Default value if the key doesn't exist.
     * @return int|null
     */
    public function get_int(string $key, $default_value = null);

    /**
     * Get a boolean value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param bool  $default_value Default value if the key doesn't exist.
     * @return bool
     */
    public function get_bool(string $key, bool $default_value = false);

    /**
     * Get a float value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param float|null  $default_value Default value if the key doesn't exist.
     * @return float|null
     */
    public function get_float(string $key, $default_value = null);


    /**
     * Get a money for storage.
     *
     * @since 1.0.0
     *
     * @param string $key The key to retrieve.
     * @param float $default_value Default value if the key doesn't exist.
     * @return float
     */
    public function get_money(string $key, $default_value = 0);

    /**
     * Get an array value.
     *
     * @since 1.0.0
     *
     * @param string $key     The key to retrieve.
     * @param array|null $default_value Default value if the key doesn't exist.
     * @return array|null
     */
    public function get_array(string $key, $default_value = null);
}
