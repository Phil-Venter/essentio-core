<?php

use Essentio\Core\Argument;
use Essentio\Core\Container;
use Essentio\Core\Environment;
use Essentio\Core\Helper;
use Essentio\Core\Jwt;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\RouteInterface;
use Essentio\Core\Router;
use Essentio\Core\Session;
use Essentio\Core\Template;

/**
 * Resolve a class from the container.
 *
 * @template T
 * @param class-string<T> $abstract
 * @return T
 */
function app(string $abstract): object
{
    return Container::instance()->get($abstract);
}

/**
 * Instantiate a class with dependencies.
 *
 * @template T
 * @param class-string<T> $abstract
 * @param array<string, mixed>|list<mixed> $dependencies
 * @return T
 */
function map(string $abstract, array $dependencies = []): object
{
    return Container::instance()->get($abstract, $dependencies);
}

/**
 * Register a binding into the container.
 *
 * @param string $abstract
 * @param callable():mixed|string|null $concrete
 * @return void
 */
function bind(string $abstract, callable|string|null $concrete = null): void
{
    Container::instance()->bind($abstract, $concrete);
}

/**
 * Register a singleton into the container.
 *
 * @param string $abstract
 * @param callable():mixed|string|null $concrete
 * @return void
 */
function once(string $abstract, callable|string|null $concrete = null): void
{
    Container::instance()->once($abstract, $concrete);
}

/**
 * Get the absolute path from base path.
 *
 * @param string $path
 * @return string
 */
function base_path(string $path): string
{
    return app(Helper::class)->fromBase($path);
}

/**
 * Get environment variable from .env file.
 *
 * @param string $key
 * @return mixed
 */
function env(string $key): mixed
{
    return app(Environment::class)->get($key);
}

/**
 * Get CLI argument by key.
 *
 * @param int|string $key
 * @return mixed
 */
function arg(int|string $key): mixed
{
    return app(Argument::class)->get($key);
}

/**
 * Register and execute a CLI command.
 *
 * @param string $name
 * @param callable(Argument): int|void $handle
 * @return void
 */
function command(string $name, callable $handle): void
{
    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    exit(is_int($result = $handle($argument)) ? $result : 0);
}

/**
 * Get the request instance or a specific key from it.
 *
 * @template T as string
 * @param T $key
 * @return (T is '' ? Request : mixed)
 */
function request(string $key = ""): mixed
{
    return func_num_args() ? app(Request::class)->get($key) : app(Request::class);
}

/**
 * Get an input field from the request body or parameters.
 *
 * @param string $field
 * @return mixed
 */
function input(string $field): mixed
{
    return app(Request::class)->input($field);
}

/**
 * Sanitize and validate user input.
 *
 * @template T as string
 * @param array<T, callable(mixed): mixed>|array<T, list<callable(mixed): mixed>> $rules
 * @param callable(array<T, list<string>>): void $callback
 * @return array<T, mixed>|false
 */
function sanitize(array $rules, callable $callback): array|false
{
    if (!($data = app(Request::class)->sanitize($rules))) {
        $callback(app(Request::class)->errors);
    }

    return $data;
}

/**
 * Get or set a session value.
 *
 * @param string $key
 * @param mixed $value
 * @return mixed
 */
function session(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->get($key) : app(Session::class)->set($key, $value);
}

/**
 * Get or set a flash session value.
 *
 * @param string $key
 * @param mixed $value
 * @return mixed
 */
function flash(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->getFlash($key) : app(Session::class)->setFlash($key, $value);
}

/**
 * Generate or verify a CSRF token.
 *
 * @template T as string
 * @param T $csrf
 * @return (T is '' ? string : bool)
 */
function csrf(string $csrf = ""): string|bool
{
    return func_num_args() ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

/**
 * Encode or decode a JWT payload.
 *
 * @template T of array|string
 * @param T $payload
 * @return (T is string ? array : string)
 */
function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}

/**
 * Register middleware globally or scoped within a group.
 *
 * @param callable(Request, callable(Request): Response): Response $middleware
 * @return void
 */
function middleware(callable $middleware): void
{
    Router::middleware($middleware);
}

/**
 * Define a route group with shared prefix.
 *
 * @param string $prefix
 * @param callable(): void $group
 * @return void
 */
function group(string $prefix, callable $group): void
{
    Router::group($prefix, $group);
}

/**
 * Register a GET route.
 *
 * @param string $path
 * @param callable(Request): mixed $handle
 * @return RouteInterface
 */
function get(string $path, callable $handle): RouteInterface
{
    return Router::route("GET", $path, $handle);
}

/**
 * Register a POST route.
 *
 * @param string $path
 * @param callable(Request): mixed $handle
 * @return RouteInterface
 */
function post(string $path, callable $handle): RouteInterface
{
    return Router::route("POST", $path, $handle);
}

/**
 * Register a PUT route.
 *
 * @param string $path
 * @param callable(Request): mixed $handle
 * @return RouteInterface
 */
function put(string $path, callable $handle): RouteInterface
{
    return Router::route("PUT", $path, $handle);
}

/**
 * Register a PATCH route.
 *
 * @param string $path
 * @param callable(Request): mixed $handle
 * @return RouteInterface
 */
function patch(string $path, callable $handle): RouteInterface
{
    return Router::route("PATCH", $path, $handle);
}

/**
 * Register a DELETE route.
 *
 * @param string $path
 * @param callable(Request): mixed $handle
 * @return RouteInterface
 */
function delete(string $path, callable $handle): RouteInterface
{
    return Router::route("DELETE", $path, $handle);
}

/**
 * Generate a named route URL.
 *
 * @param string $name
 * @param array<string, scalar> $params
 * @return string
 */
function named_url(string $name, array $params = []): string
{
    return Router::makeUrlByName($name, $params);
}

/**
 * Render a PHP template to string.
 *
 * @param string $template
 * @param array<string, mixed> $data
 * @return string
 */
function render(string $template, array $data = []): string
{
    return map(Template::class, [$template])->render($data);
}

/**
 * Create a redirect response.
 *
 * @param string $uri
 * @param int $status
 * @return Response
 */
function redirect(string $uri, int $status = 302): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Location" => $uri]);
}

/**
 * Create an HTML response.
 *
 * @param string $html
 * @param int $status
 * @return Response
 */
function html(string $html, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/html"])
        ->setBody($html);
}

/**
 * Create a JSON response.
 *
 * @param mixed $data
 * @param int $status
 * @return Response
 */
function json(mixed $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/json"])
        ->setBody(json_encode($data));
}

/**
 * Create a plain text response.
 *
 * @param string $text
 * @param int $status
 * @return Response
 */
function text(string $text, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}

/**
 * Render a view and return an HTML response.
 *
 * @param string $template
 * @param array<string, mixed> $data
 * @param int $status
 * @return Response
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}

/**
 * Conditionally throw an exception.
 *
 * @param bool $condition
 * @param Throwable|string $e
 * @return void
 * @throws Throwable
 */
function throw_if(bool $condition, Throwable|string $e): void
{
    if ($condition) {
        throw $e instanceof Throwable ? $e : new Exception($e);
    }
}
