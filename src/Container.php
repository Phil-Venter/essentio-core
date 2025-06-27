<?php

namespace Essentio\Core;

use RuntimeException;

class Container
{
    protected static $instance;

    protected array $bindings = [];

    protected array $cache = [];

    public static function instance(): static
    {
        return static::$instance ??= new static();
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @param callable():T|class-string<T>|null $concrete
     * @return static
     */
    public function bind(string $id, callable|string|null $concrete = null): static
    {
        if (is_string($concrete ??= $id) && !class_exists($concrete, true)) {
            throw new RuntimeException("Cannot bind [{$id}] to [{$concrete}].");
        }

        $this->bindings[$id] = $concrete;
        return $this;
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @param callable():T|class-string<T>|null $concrete
     * @return static
     */
    public function once(string $id, callable|string|null $concrete = null): static
    {
        $this->cache[$id] = null;
        return $this->bind($id, $concrete);
    }

    /**
     * @template T
     * @param class-string<T> $id
     * @param array<string,mixed>|list<mixed> $dependencies
     * @return T
     */
    public function get(string $id, array $dependencies = []): object
    {
        if (!isset($this->bindings[$id])) {
            if (class_exists($id, true)) {
                return new $id(...$dependencies);
            }

            throw new RuntimeException("Service [{$id}] is not bound and cannot be instantiated.");
        }

        $once = $once = array_key_exists($id, $this->cache);

        if ($once && $this->cache[$id] !== null) {
            return $this->cache[$id];
        }

        $concrete = $this->bindings[$id];
        $resolved = is_string($concrete) ? new $concrete(...$dependencies) : $concrete(...$dependencies);

        if ($once) {
            $this->cache[$id] = $resolved;
        }

        return $resolved;
    }

    /**
     * @param class-string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }
}
