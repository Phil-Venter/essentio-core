<?php
declare(strict_types=1);

namespace Essentio;

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
     * @template T
     * @param class-string<T> $id
     * @param callable():T|class-string<T>|null $concrete
     */
    public function bind(string $id, callable|string|null $concrete = null): void
    {
        /** @var string $concrete */
        if (is_string($concrete ??= $id) && !class_exists($concrete, true)) {
            throw new FrameworkException(sprintf("Cannot bind [%s] to [%s].", $id, $concrete));
        }

        $this->bindings[$id] = $concrete;
    }

    /**
     * Bind a singleton to the container.
     *
     * @template T
     * @param class-string<T> $id
     * @param callable():T|class-string<T>|null $concrete
     */
    public function once(string $id, callable|string|null $concrete = null): void
    {
        $this->cache[$id] = null;
        $this->bind($id, $concrete);
    }

    /**
     * Resolve a class or binding from the container.
     *
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

            throw new FrameworkException(sprintf("Service [%s] is not bound and cannot be instantiated.", $id));
        }

        $once = array_key_exists($id, $this->cache);

        if ($once && $this->cache[$id] !== null) {
            return $this->cache[$id];
        }

        $concrete = $this->bindings[$id];
        $resolved = is_string($concrete) ? new $concrete(...$dependencies) : $concrete(...$dependencies);

        if ($once) {
            $this->cache[$id] = $resolved;
        }

        /**
         * @template T
         * @var T $resolved
         */
        return $resolved;
    }
}
