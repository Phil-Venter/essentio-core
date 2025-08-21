<?php

declare(strict_types=1);

use Essentio\Container;
use Essentio\Application;
use Essentio\Environment;

/**
 * Resolve a class from the container.
 *
 * @template T of object
 * @template U of class-string<T>|string
 * @param U $id
 * @return (U is class-string<T> ? T : object)
 */
function app(string $id): object
{
    return Container::instance()->get($id);
}

/**
 * Instantiate a class with dependencies.
 *
 * @template T of object
 * @template U of class-string<T>|string
 * @param U $id
 * @param array<string,mixed>|list<mixed> $dependencies
 * @return (U is class-string<T> ? T : object)
 */
function make(string $id, array $dependencies = []): object
{
    return Container::instance()->get($id, $dependencies);
}

/**
 * Register a binding into the container.
 *
 * @template T of object
 * @param class-string<T>|string $id
 * @param callable():T|T|class-string<T>|string|null $concrete
 */
function bind(string $id, callable|string|null $concrete = null): void
{
    Container::instance()->bind($id, $concrete);
}

/**
 * Register a singleton into the container.
 *
 * @template T of object
 * @param class-string<T>|string $id
 * @param callable():T|T|class-string<T>|null $concrete
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
