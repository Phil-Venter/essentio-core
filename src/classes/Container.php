<?php

namespace Zen\Core;

/**
 * A simple dependency injection container that allows binding of
 * factories to service identifiers and resolves them when needed.
 */
class Container
{
    /** @var array<string, object{factory: callable, once: bool}> */
    protected array $bindings = [];

    /**
     * @template T of object
     * @var array<class-string<T>, T>
     */
    protected array $cache = [];

    /**
     * Bind a service to the container.
     *
     * Registers a service with a unique identifier and a factory callable
     * responsible for creating the service instance.
     *
     * @template T of object
     * @param class-string<T>    $id
     * @param callable(static):T $factory
     * @return object{factory: callable, once: bool}
     */
    public function bind(string $id, callable $factory): object
    {
        return $this->bindings[$id] = new class($factory) {
            public bool $once = false;

            public function __construct(
                public $factory
            ) {}
        };
    }

    /**
     * Retrieve a service from the container.
     *
     * Resolves and returns a service based on its identifier. If the service
     * has been previously resolved and marked as a singleton (via the 'once' flag),
     * the cached instance is returned.
     *
     * @template T of object
     * @param  class-string<T>|string $id
     * @return ($id is class-string<T> ? T : object)
     * @throws \RuntimeException
     */
    public function get(string $id): object
    {
        if (!isset($this->bindings[$id])) {
            throw new \RuntimeException(\sprintf('No binding for %s exists', $id));
        }

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $binding  = $this->bindings[$id];
        $resolved = \call_user_func($binding->factory, $this);

        if ($binding->once) {
            $this->cache[$id] = $resolved;
        }

        return $resolved;
    }
}
