<?php

use Essentio\Core\Application;
use Essentio\Core\Argument;
use Essentio\Core\Environment;
use Essentio\Core\Jwt;
use Essentio\Core\Request;
use Essentio\Core\Response;
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
    return Application::$container->resolve($abstract);
}

/**
 * @template T
 * @param class-string<T> $abstract
 * @param array<string,mixed>|list<mixed> $dependencies
 * @return T
 */
function map(string $abstract, array $dependencies = []): object
{
    return Application::$container->resolve($abstract, $dependencies);
}

function bind(string $abstract, callable|string|null $concrete = null): void
{
    Application::$container->bind($abstract, $concrete);
}

function once(string $abstract, callable|string|null $concrete = null): void
{
    Application::$container->once($abstract, $concrete);
}

function base(string $path): string
{
    return Application::fromBase($path);
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

function request(string $key = ""): mixed
{
    return func_num_args() ? app(Request::class) : app(Request::class)->get($key);
}

function input(string $field): mixed
{
    return app(Request::class)->input($field);
}

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

function csrf(string $csrf = ""): string|bool
{
    return func_num_args() ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}

function middleware(callable $middleware): void
{
    app(Router::class)->middleware($middleware);
}

function get(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("GET", $path, $handle, $middleware);
}

function post(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("POST", $path, $handle, $middleware);
}

function put(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("PUT", $path, $handle, $middleware);
}

function patch(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("PATCH", $path, $handle, $middleware);
}

function delete(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("DELETE", $path, $handle, $middleware);
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
