<?php

declare(strict_types=1);

namespace Essentio;

use Throwable;

use function array_key_exists;
use function class_exists;
use function is_callable;
use function is_object;
use function is_string;
use function sprintf;

/**
 * @api
 */
final class Container
{
    protected const BOUND_CLASS = "__new__";

    protected const BOUND_FACTORY = "__call__";

    protected const BOUND_ALIAS = "__alias__";

    private static $instance;

    private array $bindings = [];

    private array $cache = [];

    /**
     * Get the container singleton instance.
     */
    public static function instance(): static
    {
        return static::$instance ??= new self();
    }

    /**
     * Bind a class or factory to the container.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @param callable():T|T|class-string<T>|string|null $concrete
     */
    public function bind(string $id, callable|object|string|null $concrete = null): void
    {
        $concrete ??= $id;

        switch (true) {
            case is_string($concrete) && isset($this->bindings[$concrete]):
                $this->bindings[$id] = [self::BOUND_ALIAS, $concrete];
                break;
            case is_string($concrete) && class_exists($concrete, true):
                $this->bindings[$id] = [self::BOUND_CLASS, $concrete];
                break;
            case is_callable($concrete):
                $this->bindings[$id] = [self::BOUND_FACTORY, $concrete];
                break;
            case is_object($concrete):
                $this->cache[$id] = $concrete;
                break;
            default:
                throw new FrameworkException(sprintf("Service [%s] cannot be bound.", $id));
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
     * @template U of class-string<T>|string
     * @param U $id
     * @param array<string,mixed>|list<mixed> $dependencies
     * @return (U is class-string<T> ? T : object)
     */
    public function get(string $id, array $dependencies = []): object
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        if (!array_key_exists($id, $this->bindings)) {
            if (class_exists($id, true)) {
                try {
                    return new $id(...$dependencies);
                } catch (Throwable $e) {
                    throw new FrameworkException(sprintf("Service [%s] could not be instantiated.", $id), 0, $e);
                }
            }

            throw new FrameworkException(sprintf("Service [%s] is not bound and cannot be instantiated.", $id));
        }

        [$type, $binding] = $this->bindings[$id];

        if ($type === self::BOUND_ALIAS) {
            return $this->get($binding, $dependencies);
        }

        try {
            $resolved = $type === self::BOUND_CLASS ? new $binding(...$dependencies) : $binding($this, ...$dependencies);
        } catch (Throwable $throwable) {
            throw new FrameworkException(sprintf("Service [%s] could not be instantiated.", $id), 0, $throwable);
        }

        if (!is_object($resolved)) {
            throw new FrameworkException(sprintf("Service [%s] did not resolve to an object.", $id));
        }

        if (array_key_exists($id, $this->cache)) {
            $this->cache[$id] = $resolved;
        }

        return $resolved;
    }
}
