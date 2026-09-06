<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Elemacy\Core\Contracts\Support\Arrayable;
use Elemacy\Core\Contracts\Support\Jsonable;

// phpcs:ignore PHPCompatibility.Keywords.ForbiddenNamesAsDeclared.resourceFound -- soft-reserved only; never hard-reserved through PHP 8.x.
abstract class Resource implements Arrayable, Jsonable
{
    protected $resource;

    public function __construct($resource_data)
    {
        if (is_array($resource_data)) {
            $this->resource = (object) $resource_data;
        } else {
            $this->resource = $resource_data;
        }
    }

    abstract public function to_array();

    /**
     * Create a new resource instance, or return null if the resource is null.
     *
     * @param mixed $resource The resource to create a new instance of.
     *
     * @return array|null
     */
    public static function make($resource_data)
    {
        if ($resource_data === null) {
            return null;
        }

        return (new static($resource_data))->to_array();
    }

    /**
     * Converts an iterable of resources into an array of resource representations.
     *
     * This method loops over the iterable and creates a new instance of the resource
     * class for each item, then calls the to_array method on the resource to
     * obtain its representation as an array.
     *
     * @param iterable $resources The iterable of resources to convert.
     *
     * @return array The array of resource representations.
     */
    public static function collection($resources)
    {
        $data = [];

        if (empty($resources)) {
            return $data;
        }

        foreach ($resources as $resource) {
            $data[] = (new static($resource))->to_array();
        }

        return $data;
    }

    /**
     * Convert the resource to a JSON string.
     *
     * Encodes the array form of the resource for straightforward transport or
     * logging purposes.
     *
     * @return string The JSON-encoded paginator representation
     * @since 1.0.0
     */
    public function to_json()
    {
        return wp_json_encode($this->to_array());
    }

    /**
     * Check if a property exists on the underlying resource.
     *
     * This magic method allows you to check if a property exists on the underlying resource
     * as if it were a property of the current class. This provides a convenient way of
     * checking for the existence of resource properties without having to explicitly call a method.
     *
     * @param string $name The name of the property to check.
     *
     * @return bool True if the property exists, false otherwise.
     */
    public function __isset($name)
    {
        return isset($this->resource->$name);
    }

    /**
     * Dynamically pass properties of the underlying resource to the caller.
     *
     * This magic method allows you to access properties of the underlying resource
     * as if they were properties of the current class. This provides a convenient
     * way of accessing resource properties without having to explicitly call a method.
     *
     * @param string $name The name of the property to access.
     *
     * @return mixed The value of the accessed property.
     */
    public function __get($name)
    {
        return $this->resource->$name ?? null;
    }

    /**
     * Dynamically pass method calls of the underlying resource to the caller.
     *
     * This magic method allows you to call methods of the underlying resource
     * as if they were methods of the current class. This provides a convenient
     * way of accessing resource methods without having to explicitly call a method.
     *
     * @param string $method The name of the method to access.
     * @param array $args The arguments to pass to the method.
     *
     * @return mixed The return value of the accessed method.
     */
    public function __call($method, $args)
    {
        return $this->resource->$method(...$args);
    }
}
