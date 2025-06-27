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
 * @template T
 * @param class-string<T> $abstract
 * @return T
 */
function app(string $abstract): object
{
    return Container::instance()->get($abstract);
}

/**
 * @template T
 * @param class-string<T> $abstract
 * @param array<string,mixed>|list<mixed> $dependencies
 * @return T
 */
function map(string $abstract, array $dependencies = []): object
{
    return Container::instance()->get($abstract, $dependencies);
}

function bind(string $abstract, callable|string|null $concrete = null): void
{
    Container::instance()->bind($abstract, $concrete);
}

function once(string $abstract, callable|string|null $concrete = null): void
{
    Container::instance()->once($abstract, $concrete);
}

function base_path(string $path): string
{
    return app(Helper::class)->fromBase($path);
}

function env(string $key): mixed
{
    return app(Environment::class)->get($key);
}

function arg(int|string $key): mixed
{
    return app(Argument::class)->get($key);
}

function command(string $name, callable $handle): void
{
    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    exit(is_int($result = $handle($argument)) ? $result : 0);
}

/**
 * @template T as string
 * @param T $key
 * @return (T is '' ? Request : mixed)
 */
function request(string $key = ""): mixed
{
    return func_num_args() ? app(Request::class) : app(Request::class)->get($key);
}

function input(string $field): mixed
{
    return app(Request::class)->input($field);
}

/**
 * @template T as string
 * @param array<T, callable(mixed): mixed> $rules
 * @return array<T, mixed>|false
 */
function sanitize(array $rules): array|false
{
    return app(Request::class)->sanitize($rules);
}

function session(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->get($key) : app(Session::class)->set($key, $value);
}

function flash(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->getFlash($key) : app(Session::class)->setFlash($key, $value);
}

/**
 * @template T as string
 * @param T $csrf
 * @return (T is '' ? string : bool)
 */
function csrf(string $csrf = ""): string|bool
{
    return func_num_args() ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

/**
 * @template T of array|string
 * @param T $payload
 * @return (T is string ? array : string)
 */
function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}

/**
 * @param callable(Request, callable(Request): Response): Response $middleware
 */
function middleware(callable $middleware): void
{
    Router::middleware($middleware);
}

/**
 * @param string $path
 * @param callable $handle
 * @return Route
 */
function get(string $path, callable $handle): RouteInterface
{
    return Router::route("GET", $path, $handle);
}

/**
 * @param string $path
 * @param callable $handle
 * @return Route
 */
function post(string $path, callable $handle): RouteInterface
{
    return Router::route("POST", $path, $handle);
}

/**
 * @param string $path
 * @param callable $handle
 * @return Route
 */
function put(string $path, callable $handle): RouteInterface
{
    return Router::route("PUT", $path, $handle);
}

/**
 * @param string $path
 * @param callable $handle
 * @return Route
 */
function patch(string $path, callable $handle): RouteInterface
{
    return Router::route("PATCH", $path, $handle);
}

/**
 * @param string $path
 * @param callable $handle
 * @return Route
 */
function delete(string $path, callable $handle): RouteInterface
{
    return Router::route("DELETE", $path, $handle);
}

function named_url(string $name, array $params = []): string
{
    return app(Router::class)->makeUrlByName($name, $params);
}

function render(string $template, array $data = []): string
{
    return map(Template::class, [$template])->render($data);
}

function redirect(string $uri, int $status = 302): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Location" => $uri]);
}

function html(string $html, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/html"])
        ->setBody($html);
}

function json(mixed $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/json"])
        ->setBody(json_encode($data));
}

function text(string $text, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}

function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}

function throw_if(bool $condition, Throwable|string $e): void
{
    if ($condition) {
        throw $e instanceof Throwable ? $e : new Exception($e);
    }
}
