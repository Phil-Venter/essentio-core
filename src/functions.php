<?php

use Essentio\Core\Application;
use Essentio\Core\Argument;
use Essentio\Core\Environment;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\Router;
use Essentio\Core\Template;

/**
 * @template T
 * @param class-string<T> $abstract
 * @return T
 */
function app(string $abstract = ""): object
{
    return func_num_args() ? Application::$container : Application::$container->resolve($abstract);
}

function map(string $abstract, array $dependencies = []): object
{
    return app()->resolve($abstract, $dependencies);
}

function bind(string $abstract, callable|string|null $concrete = null): void
{
    app()->bind($abstract, $concrete);
}

function once(string $abstract, callable|string|null $concrete = null): void
{
    app()->once($abstract, $concrete);
}

function env(string $key, mixed $default = null): mixed
{
    return app(Environment::class)->get($key, $default);
}

function arg(int|string $key, mixed $default = null): mixed
{
    return app(Argument::class)->get($key, $default);
}

function command(string $name, callable $handle): void
{
    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    exit(is_int($result = $handle($argument)) ? $result : 0);
}

function request(string $key = "", mixed $default = null): mixed
{
    return func_num_args() ? app(Request::class) : app(Request::class)->get($key, $default);
}

function input(string $field, mixed $default = null): mixed
{
    return app(Request::class)->input($field, $default);
}

function sanitize(array $rules): array|false
{
    return app(Request::class)->sanitize($rules);
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
        ->appendHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}

function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}
