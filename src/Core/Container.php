<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Exception;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Minimal reflection container: auto-wires a class from its constructor type
 * hints. Lifetime is chosen explicitly at the call site:
 *
 *  - singleton($class): one shared instance, built once and reused on every
 *    later call. Use for stateless services/collaborators.
 *  - make($class): a fresh instance on every call. Its constructor dependencies
 *    are still resolved as shared singletons. Use for per-request handlers
 *    (e.g. controllers) that consume shared services.
 *
 * A single shared container (Container::instance()) backs Route and AjaxRouter;
 * the singleton cache lives for the (short-lived) WordPress request.
 */
class Container
{
    protected static ?self $instance = null;

    /** @var array<class-string,object> */
    protected array $shared = [];

    /**
     * Private — use instance().
     */
    protected function __construct() {}

    /**
     * Returns the shared Container instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Whether a shared instance of the class is already cached.
     *
     * @param string $class_name Fully-qualified class name.
     * @return bool
     */
    public function has(string $class_name): bool
    {
        return isset($this->shared[$class_name]);
    }

    /**
     * Builds a fresh instance every call (constructor dependencies are resolved
     * as shared singletons).
     *
     * @param string $class_name Fully-qualified class name to instantiate.
     * @return object The resolved instance.
     * @throws Exception If the class can't be resolved (see build()).
     */
    public function make(string $class_name): object
    {
        return $this->build($class_name, []);
    }

    /**
     * Returns the one shared instance, building and caching it on first request.
     *
     * @param string $class_name Fully-qualified class name to instantiate.
     * @return object The shared instance.
     * @throws Exception If the class can't be resolved (see build()).
     */
    public function singleton(string $class_name): object
    {
        return $this->resolve_shared($class_name, []);
    }

    /**
     * Resolves a class as a shared instance, building and caching it on first request.
     *
     * @param string   $class_name Fully-qualified class name to instantiate.
     * @param string[] $resolving  Classes on the current build path, for cycle detection.
     * @return object The shared instance.
     * @throws Exception If the class can't be resolved (see build()).
     */
    protected function resolve_shared(string $class_name, array $resolving): object
    {
        if (!isset($this->shared[$class_name])) {
            $this->shared[$class_name] = $this->build($class_name, $resolving);
        }

        return $this->shared[$class_name];
    }

    /**
     * Instantiates a class, recursively resolving its constructor dependencies
     * (as shared instances) from their type hints.
     *
     * @param string   $class_name Fully-qualified class name to instantiate.
     * @param string[] $resolving  Classes on the current build path, for cycle detection.
     * @return object The new instance.
     * @throws Exception If the class doesn't exist, is abstract, has a
     *                    non-public constructor, has a circular dependency, or
     *                    a constructor parameter isn't a single class type hint.
     */
    protected function build(string $class_name, array $resolving): object
    {
        if (in_array($class_name, $resolving, true)) {
            /* translators: %s: Class name */
            throw new Exception(sprintf(esc_html__('Circular dependency detected for class %s.', 'elemacy'), esc_html($class_name)));
        }

        if (!class_exists($class_name)) {
            /* translators: %s: Class name */
            throw new Exception(sprintf(esc_html__('Class %s does not exist.', 'elemacy'), esc_html($class_name)));
        }

        $reflector = new ReflectionClass($class_name);

        if ($reflector->isAbstract()) {
            /* translators: %s: Class name */
            throw new Exception(sprintf(esc_html__('Class %s is abstract and cannot be instantiated.', 'elemacy'), esc_html($class_name)));
        }

        $constructor = $reflector->getConstructor();

        if (!$constructor) {
            return new $class_name();
        }

        if (!$constructor->isPublic()) {
            /* translators: %s: Class name */
            throw new Exception(sprintf(esc_html__('Class %s has a non-public constructor and cannot be instantiated.', 'elemacy'), esc_html($class_name)));
        }

        $resolving[] = $class_name;
        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType) {
                /* translators: %s: Parameter name */
                throw new Exception(sprintf(esc_html__('Parameter %s must have a single class type hint. Union or intersection types are not supported.', 'elemacy'), esc_html($parameter->getName())));
            }

            if ($type->isBuiltin()) {
                /* translators: %s: Parameter name */
                throw new Exception(sprintf(esc_html__('Parameter %s must be a class type, not a built-in type. Please specify a valid class dependency.', 'elemacy'), esc_html($parameter->getName())));
            }

            $dependencies[] = $this->resolve_shared($type->getName(), $resolving);
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
