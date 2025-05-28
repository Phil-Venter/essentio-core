<?php

namespace Essentio\Core;

use Closure;
use RuntimeException;

use function array_key_exists;
use function class_exists;
use function is_string;
use function is_subclass_of;

class Container
{
    /** @var array<class-string, Closure|class-string|null> */
    protected array $bindings = [];

    /**
     * @template T of object
     * @var array<class-string<T>, T|null>
     */
    protected array $cache = [];

    /**
     * Binds a class or closure to an abstract type.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param Closure():T|class-string<T>|null $concrete
     * @return $this
     * @throws RuntimeException
     */
    public function bind(string $abstract, Closure|string|null $concrete = null): self
    {
        if (
            is_string($concrete) &&
            $abstract !== $concrete &&
            (!class_exists($concrete) || !is_subclass_of($concrete, $abstract))
        ) {
            throw new RuntimeException("Cannot bind [{$abstract}] to [{$concrete}].");
        }

        $this->bindings[$abstract] = $concrete ?? $abstract;
        return $this;
    }

    /**
     * Binds a singleton service to the container.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param Closure():T|class-string<T>|null $concrete
     * @return $this
     */
    public function once(string $abstract, Closure|string|null $concrete = null): self
    {
        $this->cache[$abstract] = null;
        return $this->bind($abstract, $concrete);
    }

    /**
     * Resolves a service instance from the container.
     *
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param array<mixed> $dependencies Optional constructor arguments.
     * @return T|object Resolved service instance.
     * @throws RuntimeException If the service cannot be instantiated.
     */
    public function resolve(string $abstract, array $dependencies = []): object
    {
        if (!isset($this->bindings[$abstract])) {
            if (class_exists($abstract, true)) {
                return new $abstract(...$dependencies);
            }

            throw new RuntimeException("Service [{$abstract}] is not bound and cannot be instantiated.");
        }

        $once = array_key_exists($abstract, $this->cache);

        if ($once && $this->cache[$abstract] !== null) {
            return $this->cache[$abstract];
        }

        $resolved =
            $this->bindings[$abstract] instanceof Closure
                ? $this->bindings[$abstract](...$dependencies)
                : new ($this->bindings[$abstract])(...$dependencies);

        if ($once) {
            $this->cache[$abstract] = $resolved;
        }

        return $resolved;
    }
}
