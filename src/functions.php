<?php

use Essentio\Core\Argument;
use Essentio\Core\Container;
use Essentio\Core\Environment;
use Essentio\Core\Helper;
use Essentio\Core\Jwt;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\Router;
use Essentio\Core\RouterRoute;
use Essentio\Core\Session;
use Essentio\Core\Template;

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
    return app(Helper::class)->fromBase($path);
}

/**
 * Get environment variable from .env file.
 */
function env(string $key): mixed
{
    return app(Environment::class)->get($key);
}

/**
 * Get CLI argument by key.
 */
function arg(int|string $key): mixed
{
    return app(Argument::class)->get($key);
}

/**
 * Register and execute a CLI command.
 */
function command(string $name, callable $handle): void
{
    if (($argument = app(Argument::class))->command === $name) {
        exit(is_int($result = $handle($argument)) ? $result : 0);
    }
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
    return func_num_args() !== 0 ? app(Request::class)->get($key) : app(Request::class);
}

/**
 * Get an input field from the request body or parameters.
 */
function input(string $field): mixed
{
    return app(Request::class)->input($field);
}

/**
 * Sanitize and validate user input.
 *
 * @template T as string
 * @param array<T,callable>|array<T,list<callable>> $rules
 * @param callable(array<string,list<string>>):void $callback
 * @return array<T,mixed>|false
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
 */
function session(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->get($key) : app(Session::class)->set($key, $value);
}

/**
 * Get or set a flash session value.
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
    return func_num_args() !== 0 ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

/**
 * Encode or decode a JWT payload.
 *
 * @template T of array<string,mixed>|string
 * @param T $payload
 * @return (T is string ? array : string)
 */
function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}

/**
 * Register middleware globally or scoped within a group.
 */
function middleware(callable $middleware): void
{
    app(Router::class)->middleware($middleware);
}

/**
 * Define a route group with shared prefix.
 */
function group(string $prefix, callable $group): void
{
    app(Router::class)->group($prefix, $group);
}

/**
 * Register a GET route.
 */
function get(string $path, callable $handle): RouterRoute
{
    return app(Router::class)->route("GET", $path, $handle);
}

/**
 * Register a POST route.
 */
function post(string $path, callable $handle): RouterRoute
{
    return app(Router::class)->route("POST", $path, $handle);
}

/**
 * Register a PUT route.
 */
function put(string $path, callable $handle): RouterRoute
{
    return app(Router::class)->route("PUT", $path, $handle);
}

/**
 * Register a PATCH route.
 */
function patch(string $path, callable $handle): RouterRoute
{
    return app(Router::class)->route("PATCH", $path, $handle);
}

/**
 * Register a DELETE route.
 */
function delete(string $path, callable $handle): RouterRoute
{
    return app(Router::class)->route("DELETE", $path, $handle);
}

/**
 * Generate a named route URL.
 *
 * @param array<string,scalar> $params
 */
function named_url(string $name, array $params = []): string
{
    return app(Router::class)->makeUrlByName($name, $params);
}

/**
 * Render a PHP template to string.
 *
 * @param array<string,mixed> $data
 */
function render(string $template, array $data = []): string
{
    return make(Template::class, [$template])->render($data);
}

/**
 * Create a redirect response.
 */
function redirect(string $uri, int $status = 302): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Location" => $uri]);
}

/**
 * Create an HTML response.
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
 */
function json(mixed $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/json"])
        ->setBody(json_encode($data, JSON_THROW_ON_ERROR));
}

/**
 * Create a plain text response.
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
 * @param array<string,mixed> $data
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
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
