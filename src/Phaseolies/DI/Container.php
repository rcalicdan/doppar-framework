<?php

namespace Phaseolies\DI;

use ArrayAccess;
use Phaseolies\DI\Attributes\Immutable;

/**
 * @implements ArrayAccess<string, mixed>
 */
class Container implements ArrayAccess
{
    /**
     * Array to hold service definitions.
     *
     * @var array<string, array{concrete: mixed, singleton: bool}>
     */
    private array $bindings = [];

    /**
     * Array to hold singleton instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Array to track currently resolving classes (for circular dependency detection)
     *
     * @var array<string, bool>
     */
    private array $resolving = [];

    /**
     * The container instance.
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * The set of binding keys that existed the moment boot finished.
     *
     * @var array<string, true>
     */
    private array $bootBindingKeys = [];

    /**
     * Whether snapshotBootBindings() has run yet.
     *
     * @var bool
     */
    private bool $bootBindingsSnapshotted = false;

    public function __construct()
    {
        $this->resolving = [];
    }

    /**
     * Prevent cloning of the container instance
     */
    public function __clone() {}

    /**
     * Prevent unserialization of the container instance
     */
    public function __wakeup() {}

    /**
     * Check if a binding exists at the specified offset.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has((string) $offset);
    }

    /**
     * Get the binding value at the specified offset.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get((string) $offset);
    }

    /**
     * Set a binding at the specified offset.
     *
     * @param mixed $offset
     * @param mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->bind((string) $offset, $value);
    }

    /**
     * Unset/remove a binding at the specified offset.
     *
     * @param mixed $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->bindings[$offset], $this->instances[$offset]);
    }

    /**
     * Bind a service to the container.
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     * @param bool $singleton
     * @return void
     */
    public function bind(string $abstract, callable|string|null $concrete = null, bool $singleton = false): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => $singleton
        ];

        if ($singleton) {
            $this->instances[$abstract] = null;
        }
    }

    /**
     * Bind a singleton service to the container.
     *
     * @param string $abstract
     * @param callable|string|null $concrete
     * @return void
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Bind an instance as a singleton.
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;

        $this->bindings[$abstract] = [
            'concrete' => fn() => $instance,
            'singleton' => true
        ];
    }

    /**
     * Resolve a service from the container.
     *
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return ($abstract is class-string<T> ? T : mixed)
     * @throws \RuntimeException
     */
    public function get(string $abstract, array $parameters = []): mixed
    {
        if (isset($this->resolving[$abstract])) {
            throw new \RuntimeException("Circular dependency detected while resolving [{$abstract}]");
        }

        if (
            !isset($this->bindings[$abstract]) &&
            !array_key_exists($abstract, $this->instances) &&
            method_exists($this, 'loadGhostProvider')
        ) {
            $this->loadGhostProvider($abstract);
        }

        $this->resolving[$abstract] = true;

        try {
            if (isset($this->instances[$abstract])) {
                return $this->instances[$abstract];
            }

            if (isset($this->bindings[$abstract])) {
                $binding = $this->bindings[$abstract];
                $resolved = $this->resolveBinding($abstract, $binding, $parameters);

                if ($binding['singleton']) {
                    $this->instances[$abstract] = $resolved;
                }

                return $resolved;
            }

            // Fallback only: no exact instance and no explicit binding for $abstract.
            foreach ($this->instances as $instance) {
                if ($instance instanceof $abstract) {
                    return $instance;
                }
            }

            if (class_exists($abstract) || interface_exists($abstract)) {
                $resolved = $this->build($abstract, $parameters);
                return $resolved;
            }

            throw new \RuntimeException("Target [{$abstract}] is not bound in container and is not a class");
        } finally {
            unset($this->resolving[$abstract]);
        }
    }

    /**
     * Resolve a binding from the container.
     *
     * @param string $abstract
     * @param array{concrete: mixed, singleton: bool} $binding
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    private function resolveBinding(string $abstract, array $binding, array $parameters): mixed
    {
        $concrete = $binding['concrete'];

        if (is_callable($concrete)) {
            return $concrete($this, $parameters);
        }

        if (is_string($concrete) && class_exists($concrete)) {
            return $this->build($concrete, $parameters);
        }

        return $concrete;
    }

    /**
     * Resolve a class with its dependencies (alias for get)
     *
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param array<string, mixed> $parameters
     * @return ($abstract is class-string<T> ? T : mixed)
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->get($abstract, $parameters);
    }

    /**
     * Build a concrete instance with dependency injection
     *
     * @template T of object
     * @param class-string<T> $concrete
     * @param array<string, mixed> $parameters
     * @return T
     * @throws \RuntimeException
     */
    public function build(string $concrete, array $parameters = []): object
    {
        if (is_subclass_of($concrete, \Phaseolies\Database\Entity\Model::class)) {
            return new $concrete(...$parameters);
        }

        $reflector = new \ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Target [{$concrete}] is not instantiable");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            $instance = new $concrete(...$parameters);
        } else {
            $dependencies = $this->resolveDependencies(
                $constructor->getParameters(),
                $parameters,
                $concrete
            );
            $instance = $reflector->newInstanceArgs($dependencies);
        }

        $this->freezeIfImmutable($reflector, $instance);

        return $instance;
    }

    /**
     * Freeze the instance if the class has the #[Immutable] attribute and uses the EnforcesImmutability trait.
     *
     * @param \ReflectionClass<object> $reflector
     * @param object $instance
     * @return void
     */
    private function freezeIfImmutable(\ReflectionClass $reflector, object $instance): void
    {
        $hasAttribute = !empty($reflector->getAttributes(Immutable::class));
        $usesTrait    = method_exists($instance, 'freeze') && method_exists($instance, 'isFrozen');

        if ($hasAttribute && $usesTrait) {
            $instance->freeze();
        }
    }

    /**
     * Resolve constructor dependencies
     *
     * @param \ReflectionParameter[] $parameters
     * @param array<string, mixed> $primitives
     * @param string $className
     * @return array<int, mixed>
     */
    protected function resolveDependencies(array $parameters, array $primitives = [], string $className = ''): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependency = $this->resolveDependency($parameter, $primitives, $className);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Resolve a single dependency
     *
     * @param \ReflectionParameter $parameter
     * @param array<string, mixed> $primitives
     * @param string $className
     * @return mixed
     */
    protected function resolveDependency(\ReflectionParameter $parameter, array &$primitives, string $className = ''): mixed
    {
        $paramName = $parameter->getName();
        $paramType = $parameter->getType();

        // Check if we have a primitive value for this parameter
        if (!empty($primitives) && array_key_exists($paramName, $primitives)) {
            return $primitives[$paramName];
        }

        // Handle class or interface dependencies
        if ($paramType instanceof \ReflectionNamedType && !$paramType->isBuiltin()) {
            $typeName = $paramType->getName();

            // If it's nullable and not bound, return null
            if ($paramType->allowsNull() && !$this->has($typeName)) {
                return null;
            }

            return $this->get($typeName);
        }

        // Check if parameter has a default value
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        // Check for variadic parameters
        if ($parameter->isVariadic()) {
            return $primitives;
        }

        // Check if we can use positional primitives
        if (!empty($primitives)) {
            return array_shift($primitives);
        }

        $declaringClass = $parameter->getDeclaringClass();

        throw new \RuntimeException(
            "Unresolvable dependency resolving [{$parameter}] in class " .
                ($className ?: ($declaringClass ? $declaringClass->getName() : 'unknown'))
        );
    }

    /**
     * Conditionally execute bindings.
     *
     * @param callable|bool $condition
     * @return self|null
     */
    public function when(callable|bool $condition): ?self
    {
        if (is_callable($condition)) {
            $condition = $condition();
        }

        return $condition ? $this : null;
    }

    /**
     * Check if the container has a binding for the given service.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->bindings[$key]) || class_exists($key);
    }

    /**
     * Check if the container has a resolved instance.
     *
     * @param string $key
     * @return bool
     */
    public function hasInstance(string $key): bool
    {
        return isset($this->instances[$key]);
    }

    /**
     * Forget a resolved instance while keeping its binding intact.
     *
     * @param string $abstract
     * @return void
     */
    public function forgetResolved(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Freeze the current set of binding keys as "boot-time".
     *
     * @return void
     */
    public function snapshotBootBindings(): void
    {
        if ($this->bootBindingsSnapshotted) {
            return;
        }

        $this->bootBindingKeys = array_fill_keys(array_keys($this->bindings), true);
        $this->bootBindingsSnapshotted = true;
    }

    /**
     * Forget the resolved instance of every singleton bound *after* boot finished
     *
     * @return void
     */
    public function forgetRequestScopedInstances(): void
    {
        if (!$this->bootBindingsSnapshotted) {
            return;
        }

        foreach (array_keys($this->bindings) as $abstract) {
            if (!isset($this->bootBindingKeys[$abstract])) {
                unset($this->instances[$abstract]);
            }
        }
    }

    /**
     * Flush the container of all instances and bindings.
     *
     * @return void
     */
    public function flush(): void
    {
        $this->bindings = [];
        $this->instances = [];
        $this->resolving = [];
        $this->bootBindingKeys = [];
        $this->bootBindingsSnapshotted = false;
    }

    /**
     * Get all bindings.
     *
     * @return array<string, array{concrete: mixed, singleton: bool}>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get all instances.
     *
     * @return array<string, mixed>
     */
    public function getInstances(): array
    {
        return $this->instances;
    }

    /**
     * Get the container instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Set the container instance
     *
     * @param self $instance
     * @return void
     */
    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Forget the container instance
     *
     * @return void
     */
    public static function forgetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Register an existing instance as shared in the container
     *
     * @param string $abstract
     * @param mixed $instance
     * @return void
     */
    public function share(string $abstract, mixed $instance): void
    {
        $this->instance($abstract, $instance);
    }

    /**
     * Extend a binding in the container
     *
     * @param string $abstract
     * @param callable $extender
     * @return void
     */
    public function extend(string $abstract, callable $extender): void
    {
        if (!$this->has($abstract)) {
            throw new \RuntimeException("Cannot extend unbound abstract [{$abstract}]");
        }

        $previous = $this->bindings[$abstract];

        $this->bindings[$abstract] = [
            'concrete' => fn(Container $container, array $parameters = []) => $extender($container->resolveBinding($abstract, $previous, $parameters), $container),
            'singleton' => $previous['singleton']
        ];
    }

    /**
     * Run a callback with some bindings temporarily replaced, then put every one
     * of them back exactly as it was, even when the callback throws
     *
     * @param array<string, mixed> $overrides
     * @param callable(self): mixed $callback
     * @return mixed
     */
    public function using(array $overrides, callable $callback): mixed
    {
        $saved = [];

        foreach (array_keys($overrides) as $abstract) {
            $saved[$abstract] = [
                'binding' => $this->bindings[$abstract] ?? null,
                'instance' => $this->instances[$abstract] ?? null,
                'hasInstance' => array_key_exists($abstract, $this->instances),
            ];
        }

        try {
            foreach ($overrides as $abstract => $concrete) {
                unset($this->instances[$abstract]);

                if ($concrete instanceof \Closure || (is_string($concrete) && class_exists($concrete))) {
                    $this->bind($abstract, $concrete, true);
                } else {
                    $this->instance($abstract, $concrete);
                }
            }

            return $callback($this);
        } finally {
            foreach ($saved as $abstract => $state) {
                unset($this->bindings[$abstract], $this->instances[$abstract]);

                if ($state['binding'] !== null) {
                    $this->bindings[$abstract] = $state['binding'];
                }

                if ($state['hasInstance']) {
                    $this->instances[$abstract] = $state['instance'];
                }
            }
        }
    }

    /**
     * Alias a type to a different name
     *
     * @param string $abstract
     * @param string $alias
     * @return void
     */
    public function alias(string $abstract, string $alias): void
    {
        $this->bindings[$alias] = [
            'concrete' => fn(Container $container) => $container->get($abstract),
            'singleton' => false
        ];
    }

    /**
     * Call the given callback with dependency injection
     *
     * @param callable $callback
     * @param array<string, mixed> $parameters
     * @return mixed
     */
    public function call(callable $callback, array $parameters = []): mixed
    {
        if (is_array($callback)) {
            $reflection = new \ReflectionMethod($callback[0], $callback[1]);
            $context = $callback[0];
        } elseif (is_object($callback) && method_exists($callback, '__invoke')) {
            $reflection = new \ReflectionMethod($callback, '__invoke');
            $context = $callback;
        } else {
            assert($callback instanceof \Closure || is_string($callback));
            $reflection = new \ReflectionFunction($callback);
            $context = null;
        }

        $dependencies = $this->resolveDependenciesWithAttributes(
            $reflection->getParameters(),
            $parameters,
            $context
        );

        return $callback(...$dependencies);
    }

    /**
     * Resolve dependencies with attribute support
     *
     * @param \ReflectionParameter[] $parameters
     * @param array<string, mixed> $primitives
     * @param object|string|null $context
     * @return array<int, mixed>
     */
    protected function resolveDependenciesWithAttributes(array $parameters, array $primitives = [], $context = null): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $bindAttributes = $parameter->getAttributes(\Phaseolies\DI\Attributes\Bind::class);

            if (!empty($bindAttributes)) {
                $bindAttribute = $bindAttributes[0]->newInstance();
                $paramType = $parameter->getType();

                if (!$paramType instanceof \ReflectionNamedType || $paramType->isBuiltin()) {
                    throw new \RuntimeException(
                        "Parameter '\${$parameter->getName()}' must be class-typed when using #[Bind]"
                    );
                }

                $abstract = $paramType->getName();

                $bindAttribute->singleton
                    ? $this->singleton($abstract, $bindAttribute->concrete)
                    : $this->bind($abstract, $bindAttribute->concrete);

                $dependencies[] = $this->get($abstract);
                continue;
            }

            $contextClass = is_object($context) ? get_class($context) : (string) $context;

            $dependency = $this->resolveDependency($parameter, $primitives, $contextClass);
            $dependencies[] = $dependency;
        }

        return $dependencies;
    }

    /**
     * Check if a binding is a singleton
     *
     * @param string $abstract
     * @return bool
     */
    public function isSingleton(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) && $this->bindings[$abstract]['singleton'];
    }

    /**
     * Get all registered aliases
     *
     * @return array<string, array{concrete: mixed, singleton: bool}>
     */
    public function getAliases(): array
    {
        return array_filter($this->bindings, function ($binding) {
            $concrete = $binding['concrete'];
            return is_callable($concrete) && !(is_string($concrete) && class_exists($concrete));
        });
    }

    /**
     * Resolve all dependencies for a given class method
     *
     * @param string $class
     * @param string $method
     * @param array<string, mixed> $parameters
     * @return array<int, mixed>
     */
    public function resolveMethodDependencies(string $class, string $method, array $parameters = []): array
    {
        $reflection = new \ReflectionMethod($class, $method);

        return $this->resolveDependencies($reflection->getParameters(), $parameters, $class);
    }

    /**
     * Check if the container is currently resolving the given abstract
     *
     * @param string $abstract
     * @return bool
     */
    public function isResolving(string $abstract): bool
    {
        return isset($this->resolving[$abstract]);
    }

    /**
     * Register a service provider
     *
     * @param object|class-string $provider
     * @return void
     */
    public function register(object|string $provider): void
    {
        if (is_string($provider) && class_exists($provider)) {
            $provider = $this->make($provider);
        }

        if (!is_object($provider)) {
            return;
        }

        if (method_exists($provider, 'register')) {
            $provider->register($this);
        }

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }
    }

    /**
     * Flush all container bindings and instances.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->flush();
    }

    /**
     * Check if the given abstract has been resolved at least once
     *
     * @param string $abstract
     * @return bool
     */
    public function resolved(string $abstract): bool
    {
        return $this->hasInstance($abstract);
    }
}
