<?php

namespace Elemacy\Core;

defined('ABSPATH') || exit;

use Exception;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Reflection container with explicit interface-to-factory bindings.
 * Lifetime is chosen explicitly:
 *
 *  - singleton($class): one shared instance, built once and reused on every
 *    later call. Use for stateless services/collaborators.
 *  - make($class): a fresh instance on every call. Use for per-request
 *    handlers (e.g. controllers) that consume shared services.
 *
 * Without any binding, both auto-wire the concrete class from its
 * constructor type hints exactly as before this class gained bind() — every
 * nested dependency is resolved as a shared singleton unless it has its own
 * registered 'fresh' binding (design.md D7: "never assume every dependency
 * is shared"). bind() lets a service provider (task 6.2) register a
 * contract — an interface, or a concrete class whose construction needs a
 * factory rather than plain reflection (e.g. scalar configuration a
 * constructor parameter's own default cannot express) — with an explicit
 * lifetime that make()/singleton() and every nested resolution both honor.
 *
 * A single shared container (Container::instance()) backs Route and AjaxRouter;
 * the singleton/binding caches live for the (short-lived) WordPress request.
 * This is a service locator restricted to bootstrap and forwarding facades
 * (Route/AjaxRouter resolving a controller) — application/domain services
 * still receive their dependencies through their own constructors, not by
 * reaching into this container themselves.
 */
class Container
{
    public const SHARED = 'shared';
    public const FRESH = 'fresh';

    protected static $instance = null;

    /** @var array<class-string,object> */
    protected array $shared = [];

    /** @var array<class-string,array{factory: callable, lifetime: self::SHARED|self::FRESH}> */
    protected array $bindings = [];

    /**
     * Contracts currently being built, for cycle detection — instance state
     * rather than a call-stack parameter, so a cycle reached through a
     * bound factory calling back into make()/singleton() is caught exactly
     * like one reached through plain constructor auto-wiring.
     *
     * @var string[]
     */
    protected array $resolving = [];

    /**
     * use instance().
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
     * Registers an explicit factory for a contract — an interface name, or a
     * concrete class name whose construction a factory (rather than plain
     * reflection) should own. The factory receives this container, so it
     * can resolve its own further dependencies through make()/singleton()
     * rather than constructing them directly; it is responsible for any
     * scalar configuration a plain constructor default cannot express.
     *
     * Re-binding an already-bound contract replaces its factory/lifetime
     * and drops any previously cached shared instance for it.
     *
     * @param class-string $contract
     * @param callable(self): object $factory
     * @param self::SHARED|self::FRESH $lifetime
     * @throws Exception If $lifetime is neither SHARED nor FRESH.
     */
    public function bind(string $contract, callable $factory, string $lifetime = self::SHARED): void
    {
        if (!in_array($lifetime, [self::SHARED, self::FRESH], true)) {
            /* translators: %s: lifetime value */
            throw new Exception(sprintf(esc_html__('Unknown lifetime %s: use Container::SHARED or Container::FRESH.', 'elemacy'), esc_html($lifetime)));
        }

        $this->bindings[$contract] = [
            'factory' => $factory,
            'lifetime' => $lifetime,
        ];
        unset($this->shared[$contract]);
    }

    /**
     * Whether a contract has an explicit factory registered.
     */
    public function bound(string $contract): bool
    {
        return isset($this->bindings[$contract]);
    }

    /**
     * Builds a fresh instance every call — unless $class_name has an
     * explicit 'shared' binding, in which case that binding's lifetime
     * governs instead (make() requesting freshness does not override an
     * explicit shared registration).
     *
     * @param string $class_name Fully-qualified class/interface name to resolve.
     * @return object The resolved instance.
     * @throws Exception If the class/contract can't be resolved (see build()).
     */
    public function make(string $class_name): object
    {
        return $this->resolve($class_name, self::FRESH);
    }

    /**
     * Returns the one shared instance, building and caching it on first
     * request — unless $class_name has an explicit 'fresh' binding, in
     * which case that binding's lifetime governs instead.
     *
     * @param string $class_name Fully-qualified class/interface name to resolve.
     * @return object The shared instance.
     * @throws Exception If the class/contract can't be resolved (see build()).
     */
    public function singleton(string $class_name): object
    {
        return $this->resolve($class_name, self::SHARED);
    }

    /**
     * @param self::SHARED|self::FRESH $default_lifetime Lifetime to use when
     *   $class_name has no explicit binding: FRESH for make()'s own
     *   top-level call, SHARED for singleton()'s and for every auto-wired
     *   nested dependency — an explicit binding's own registered lifetime
     *   always takes priority over this default.
     * @throws Exception
     */
    protected function resolve(string $class_name, string $default_lifetime): object
    {
        if (in_array($class_name, $this->resolving, true)) {
            /* translators: %s: Class name */
            throw new Exception(sprintf(esc_html__('Circular dependency detected for class %s.', 'elemacy'), esc_html($class_name)));
        }

        if (isset($this->bindings[$class_name])) {
            return $this->resolve_binding($class_name);
        }

        if ($default_lifetime === self::FRESH) {
            return $this->tracked($class_name, fn () => $this->build($class_name));
        }

        if (!isset($this->shared[$class_name])) {
            $this->shared[$class_name] = $this->tracked($class_name, fn () => $this->build($class_name));
        }

        return $this->shared[$class_name];
    }

    protected function resolve_binding(string $class_name): object
    {
        $binding = $this->bindings[$class_name];

        if ($binding['lifetime'] === self::SHARED && isset($this->shared[$class_name])) {
            return $this->shared[$class_name];
        }

        $instance = $this->tracked($class_name, fn () => ($binding['factory'])($this));

        if ($binding['lifetime'] === self::SHARED) {
            $this->shared[$class_name] = $instance;
        }

        return $instance;
    }

    /**
     * Runs $build with $class_name pushed onto the cycle-detection stack for
     * its duration, guaranteeing it is popped again even if $build throws.
     */
    protected function tracked(string $class_name, callable $build): object
    {
        $this->resolving[] = $class_name;

        try {
            return $build();
        } finally {
            array_pop($this->resolving);
        }
    }

    /**
     * Instantiates a class via reflection, recursively resolving its
     * constructor's class-typed parameters (each through resolve(), so a
     * parameter type with its own binding is honored even here) and, for a
     * builtin-typed parameter, using its default value. A required builtin
     * parameter with no default cannot be auto-wired at all: register an
     * explicit bind() factory for $class_name instead, since a factory is
     * where scalar configuration belongs, not this reflection path.
     *
     * @param string $class_name Fully-qualified class name to instantiate.
     * @return object The new instance.
     * @throws Exception If the class/interface doesn't exist, is an unbound
     *                    interface or abstract class, has a non-public
     *                    constructor, or a required parameter cannot be
     *                    resolved.
     */
    protected function build(string $class_name): object
    {
        if (!class_exists($class_name) && !interface_exists($class_name)) {
            /* translators: %s: Class name */
            throw new Exception(sprintf(esc_html__('Class %s does not exist.', 'elemacy'), esc_html($class_name)));
        }

        $reflector = new ReflectionClass($class_name);

        if ($reflector->isInterface()) {
            /* translators: %s: Interface name */
            throw new Exception(sprintf(esc_html__('No binding is registered for contract %s. Register one with Container::bind() before resolving it.', 'elemacy'), esc_html($class_name)));
        }

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

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $this->resolve($type->getName(), self::SHARED);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();

                continue;
            }

            if (!$type instanceof ReflectionNamedType) {
                /* translators: %s: Parameter name */
                throw new Exception(sprintf(esc_html__('Parameter %s must have a single class type hint. Union or intersection types are not supported.', 'elemacy'), esc_html($parameter->getName())));
            }

            /* translators: %s: Parameter name */
            throw new Exception(sprintf(esc_html__('Parameter %s has no default value and is not a class type the container can auto-wire. Register a Container::bind() factory for this class instead — factories, not this reflection path, are responsible for scalar configuration.', 'elemacy'), esc_html($parameter->getName())));
        }

        return $reflector->newInstanceArgs($dependencies);
    }
}
