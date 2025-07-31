<?php

declare(strict_types=1);

use Essentio\Container;
use Essentio\Application;
use Essentio\Environment;

/**
 * Resolve a class from the container.
 *
 * @template T
 * @param class-string<T> $id
 * @return T
 */
function app(string $id): object
{
    return Container::instance()->get($id);
}

/**
 * Instantiate a class with dependencies.
 *
 * @template T
 * @param class-string<T> $id
 * @param array<string,mixed>|list<mixed> $dependencies
 * @return T
 */
function make(string $id, array $dependencies = []): object
{
    return Container::instance()->get($id, $dependencies);
}

/**
 * Register a binding into the container.
 *
 * @template T
 * @param class-string<T> $id
 * @param callable():T|class-string<T>|null $concrete
 */
function bind(string $id, callable|string|null $concrete = null): void
{
    Container::instance()->bind($id, $concrete);
}

/**
 * Register a singleton into the container.
 *
 * @template T
 * @param class-string<T> $id
 * @param callable():T|class-string<T>|null $concrete
 */
function once(string $id, callable|string|null $concrete = null): void
{
    Container::instance()->once($id, $concrete);
}

/**
 * Get the absolute path from base path.
 */
function base_path(string $path): string
{
    return Application::fromBase($path);
}

/**
 * Get environment variable from .env file.
 */
function env(string $key): mixed
{
    return app(Environment::class)->get($key);
}

/**
 * Conditionally throw an exception.
 *
 * @throws Throwable
 */
function throw_if(bool $condition, Throwable|string $e): void
{
    if ($condition) {
        throw $e instanceof Throwable ? $e : new Exception($e);
    }
}
