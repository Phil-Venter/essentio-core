<?php

declare(strict_types=1);

namespace Essentio;

use function array_key_exists;
use function class_exists;
use function is_callable;
use function is_object;
use function is_string;
use function sprintf;

/**
 * @api
 */
class Container
{
    protected static $instance;

    protected array $bindings = [];

    protected array $cache = [];

    /**
     * Get the container singleton instance.
     */
    public static function instance(): static
    {
        return static::$instance ??= new static();
    }

    /**
     * Bind a class or factory to the container.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @param callable():T|T|class-string<T>|null $concrete
     */
    public function bind(string $id, callable|object|string|null $concrete = null): void
    {
        $concrete ??= $id;
        $this->bindings[$id] = $concrete;

        if (!is_string($concrete) && !is_callable($concrete)) {
            $this->cache[$id] = $concrete;
        }
    }

    /**
     * Bind a singleton to the container.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @param callable():T|T|class-string<T>|null $concrete
     */
    public function once(string $id, callable|object|string|null $concrete = null): void
    {
        $this->cache[$id] = null;
        $this->bind($id, $concrete);
    }

    /**
     * Resolve a class or binding from the container.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @param array<string,mixed>|list<mixed> $dependencies
     * @return T
     */
    public function get(string $id, array $dependencies = []): object
    {
        if (!array_key_exists($id, $this->bindings)) {
            if (class_exists($id, true)) {
                /** @psalm-suppress InvalidReturnStatement */
                return new $id(...$dependencies);
            }

            throw new FrameworkException(sprintf("Service [%s] is not bound and cannot be instantiated.", $id));
        }

        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        $resolved = $this->bindings[$id];

        if (is_string($resolved) && class_exists($resolved, true)) {
            $resolved = new $resolved(...$dependencies);
        } elseif (is_callable($resolved)) {
            $resolved = $resolved(...$dependencies);
        }

        if (!is_object($resolved)) {
            throw new FrameworkException(sprintf("Service [%s] did not resolve to an object.", $id));
        }

        if (array_key_exists($id, $this->cache)) {
            $this->cache[$id] = $resolved;
        }

        /** @var T $resolved */
        return $resolved;
    }
}
