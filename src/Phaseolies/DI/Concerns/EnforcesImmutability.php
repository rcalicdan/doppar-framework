<?php

namespace Phaseolies\DI\Concerns;

use Phaseolies\DI\Exceptions\ImmutableViolationException;

/**
 * @phpstan-ignore trait.unused
 */
trait EnforcesImmutability
{
    /**
     * Holds all public property values after they are unset on freeze()
     *
     * @var array<string, mixed>
     */
    private array $__immutableProperties = [];

    /**
     * Whether this instance has been frozen by the container
     */
    private bool $__frozen = false;

    /**
     * Called by Container::freezeIfImmutable() after build() completes
     *
     * Snapshots all public instance properties into the internal map,
     * then unsets them — forcing future access through __get/__set.
     */
    public function freeze(): void
    {
        $reflector = new \ReflectionClass(static::class);

        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $name = $property->getName();

            $this->__immutableProperties[$name] = $property->isInitialized($this)
                ? $property->getValue($this)
                : null;

            unset($this->$name);
        }

        $this->__frozen = true;
    }

    /**
     * Check if the instance is frozen
     */
    public function isFrozen(): bool
    {
        return $this->__frozen;
    }

    /**
     * Serve reads from the frozen snapshot
     *
     * @throws ImmutableViolationException on write attempts after freeze()
     * @throws \RuntimeException on access to undefined properties
     */
    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->__immutableProperties)) {
            return $this->__immutableProperties[$name];
        }

        throw new \RuntimeException(
            "Property \${$name} does not exist on " . static::class
        );
    }

    /**
     * Block all writes once frozen
     *
     * @throws ImmutableViolationException
     */
    public function __set(string $name, mixed $value): void
    {
        if ($this->__frozen) {
            throw new ImmutableViolationException(static::class, $name);
        }

        $this->$name = $value;
    }

    /**
     * Forward isset() checks to the frozen map
     */
    public function __isset(string $name): bool
    {
        return isset($this->__immutableProperties[$name]);
    }

    /**
     * Block unset() — it is a mutation
     *
     * @throws ImmutableViolationException
     */
    public function __unset(string $name): void
    {
        throw new ImmutableViolationException(static::class, $name);
    }

    /**
     * Prevent cloning of frozen services
     *
     * @throws ImmutableViolationException
     */
    public function __clone(): void
    {
        if ($this->__frozen) {
            throw new ImmutableViolationException(static::class, '__clone');
        }
    }
}