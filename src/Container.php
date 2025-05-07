<?php

namespace Essentio\Core;

use RuntimeException;

use function call_user_func;
use function class_exists;
use function compact;
use function sprintf;

class Container
{
    /** @var array<string, object{factory:callable, once:bool}> */
    protected array $bindings = [];

    /**
     * @template T of object
     * @var array<class-string<T>, T>
     */
    protected array $cache = [];

    /**
     * Bind a service to the container.
     *
     * @template T of object
     * @param class-string<T> $id
     * @param callable(static):T $factory
     * @return object{factory:callable, once:bool}
     */
    public function bind(string $id, callable $factory): object
    {
        $once = false;
        return $this->bindings[$id] = (object) compact("factory", "once");
    }

    /**
     * Retrieve a service from the container.
     *
     * @template T of object
     * @param  class-string<T>|string $id
     * @return ($id is class-string<T> ? T : object)
     * @throws RuntimeException
     */
    public function resolve(string $id): object
    {
        if (!isset($this->bindings[$id])) {
            if (class_exists($id, true)) {
                return new $id();
            }

            throw new RuntimeException(sprintf("No binding for %s exists", $id));
        }

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $binding = $this->bindings[$id];
        $resolved = call_user_func($binding->factory, $this);

        if ($binding->once) {
            $this->cache[$id] = $resolved;
        }

        return $resolved;
    }
}
