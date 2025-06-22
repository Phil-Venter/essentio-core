<?php

namespace Essentio\Core;

use RuntimeException;

use function array_key_exists;
use function class_exists;
use function is_string;

class Container
{
    protected array $bindings = [];

    protected array $cache = [];

    public function bind(string $abstract, callable|string|null $concrete = null): self
    {
        $concrete ??= $abstract;

        if (is_string($concrete) && !class_exists($concrete, true)) {
            throw new RuntimeException("Cannot bind [{$abstract}] to [{$concrete}].");
        }

        $this->bindings[$abstract] = $concrete;
        return $this;
    }

    public function once(string $abstract, callable|string|null $concrete = null): self
    {
        $this->cache[$abstract] = null;
        return $this->bind($abstract, $concrete);
    }

    /**
     * @template T
     * @param class-string<T> $abstract
     * @param array<string,mixed>|list<mixed> $dependencies
     * @return T
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

        $concrete = $this->bindings[$abstract];
        $resolved = is_string($concrete) ? new $concrete(...$dependencies) : $concrete(...$dependencies);

        if ($once) {
            $this->cache[$abstract] = $resolved;
        }

        return $resolved;
    }
}
