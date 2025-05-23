<?php

use Essentio\Core\Application;
use Essentio\Core\Argument;
use Essentio\Core\Environment;
use Essentio\Core\HttpException;
use Essentio\Core\Jwt;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\Router;
use Essentio\Core\Session;

/**
 * If no identifier is provided, returns the container instance.
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return ($id is class-string<T> ? T : object)
 */
function app(?string $id = null): object
{
    return is_null($id) ? Application::$container : Application::$container->resolve($id);
}

/**
 * Attempt to resolve path from base as passed to Application factory.
 *
 * @param string $path
 * @return string
 */
function base(string $path = ""): string
{
    return Application::fromBase($path);
}

/**
 * This function fetches an environment variable from the Environment instance.
 *
 * @param ?string $key
 * @param mixed   $default
 * @return mixed
 */
function env(?string $key = null, mixed $default = null): mixed
{
    return is_null($key) ? app(Environment::class) : app(Environment::class)->get($key, $default);
}

/**
 * This function binds a service to the container using the specified identifier and factory.
 *
 * @param string   $id
 * @param callable $factory
 * @return object
 */
function bind(string $id, callable $factory): object
{
    return app()->bind($id, $factory);
}

/**
 * This function retrieves a command-line argument using the specified key.
 *
 * @param int|string|null $key
 * @param mixed           $default
 * @return mixed
 */
function arg(int|string|null $key = null, mixed $default = null): mixed
{
    return is_null($key) ? app(Argument::class) : app(Argument::class)->get($key, $default);
}

/**
 * Executes the provided command handler if the current command matches the specified name.
 *
 * @param string   $name
 * @param callable $handle
 * @return void
 */
function command(string $name, callable $handle): void
{
    if (!Application::isCli()) {
        return;
    }

    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    $result = $handle($argument);

    exit(is_int($result) ? $result : 0);
}

/**
 * Fetches a value from the current Request instance using the specified key.
 *
 * @param ?string $field
 * @param mixed   $default
 * @return mixed
 */
function request(?string $field = null, mixed $default = null): mixed
{
    return is_null($field) ? app(Request::class) : app(Request::class)->get($field, $default);
}

/**
 * Fetches a value from the current Request instance body using the specified key.
 *
 * @param string $field
 * @param mixed  $default
 * @return mixed
 */
function input(string $field, mixed $default = null): mixed
{
    return app(Request::class)->input($field, $default);
}

/**
 * Sanitizes and validates request input using field-specific callables.
 *
 * @param array<string, array<Closure>> $rules
 * @param bool|Exception $exception
 * @return array<string, mixed>|false
 */
function sanitize(array $rules, bool|Exception $exception = false): array|false
{
    $data = app(Request::class)->sanitize($rules);

    if ($exception !== false && $data === false) {
        if ($exception instanceof Exception) {
            throw $exception;
        }

        throw HttpException::new(422, implode('<br>', array_merge(...app(Request::class)->errors)));
    }

    return $data;
}

/**
 * Add middleware that will be applied globally.
 *
 * @param callable $middleware
 * @return void
 */
function middleware(callable $middleware): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->use($middleware);
}

/**
 * Groups routes under a shared prefix and middleware stack for scoped handling.
 *
 * @param string   $prefix
 * @param callable $handle
 * @param array    $middleware
 * @return void
 */
function group(string $prefix, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->group($prefix, $handle, $middleware);
}

/**
 * Create a GET method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function get(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("GET", $path, $handle, $middleware);
}

/**
 * Create a POST method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function post(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("POST", $path, $handle, $middleware);
}

/**
 * Create a PUT method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function put(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("PUT", $path, $handle, $middleware);
}

/**
 * Create a PATCH method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function patch(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("PATCH", $path, $handle, $middleware);
}

/**
 * Create a DELETE method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function delete(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("DELETE", $path, $handle, $middleware);
}

/**
 * Sets flash data if a value is provided, or retrieves and removes flash data for the given key.
 *
 * @param string $key
 * @param mixed  $value
 * @return mixed
 */
function flash(string $key, mixed $value = null): mixed
{
    if (!Application::isWeb()) {
        return null;
    }

    if (func_num_args() === 1) {
        return app(Session::class)->get($key);
    }

    app(Session::class)->flash($key, $value);
    return null;
}

/**
 * Sets session data if a value is provided, or retrieves session data for the given key.
 *
 * @param string $key
 * @param mixed  $value
 * @return mixed
 */
function session(string $key, mixed $value = null): mixed
{
    if (!Application::isWeb()) {
        return null;
    }

    if (func_num_args() === 1) {
        return app(Session::class)->get($key);
    }

    app(Session::class)->set($key, $value);
    return null;
}

/**
 * Retrieves the CSRF token from the session or generates a new one if absent.
 *
 * @return string
 */
function csrf(): ?string
{
    if (!Application::isWeb()) {
        return null;
    }

    if ($token = session('\0CSRF')) {
        return $token;
    }

    $token = bin2hex(random_bytes(32));
    session('\0CSRF', $token);
    return $token;
}

/**
 * Validates the provided CSRF token against the session token and rotates if valid.
 *
 * @param string $csrf
 * @return bool
 */
function csrf_verify(string $csrf): ?bool
{
    if (!Application::isWeb()) {
        return null;
    }

    if ($valid = hash_equals(session('\0CSRF'), $csrf)) {
        session('\0CSRF', bin2hex(random_bytes(32)));
    }

    return $valid;
}

function jwt(array $payload): ?string
{
    if (!Application::isApi()) {
        return null;
    }

    return app(Jwt::class)->encode($payload);
}

function jwt_decode(string $token): ?array
{
    if (!Application::isApi()) {
        return null;
    }

    return app(Jwt::class)->decode($token);
}

/**
 * Renders a template with the provided data.
 *
 * @param string $template
 * @param array  $data
 * @return string
 */

function render(string $template, array $data = []): string
{
    $class = \Essentio\Core\Extra\Template::class;

    if (class_exists($class)) {
        return new $class($template)->render($data);
    }

    return vsprintf($template, $data);
}

/**
 * Returns a Response instance configured to redirect to the specified URI with the given status code.
 *
 * @param string $uri
 * @param int    $status
 * @return Response
 */
function redirect(string $uri, int $status = 302): Response
{
    return new Response()->withStatus($status)->withHeaders(["Location" => $uri]);
}

/**
 * Returns a Response instance configured to send HTML content with the specified status code.
 *
 * @param string $html
 * @param int    $status
 * @return Response
 */
function html(string $html, int $status = 200): Response
{
    return new Response()
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "text/html"])
        ->withBody($html);
}

/**
 * Returns a Response instance configured to send JSON data with the specified status code.
 *
 * @param mixed $data
 * @param int   $status
 * @return Response
 */
function json(mixed $data, int $status = 200): Response
{
    return new Response()
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "application/json"])
        ->withBody(json_encode($data));
}

/**
 * Returns a Response instance configured to send plain text with the specified status code.
 *
 * @param string $text
 * @param int    $status
 * @return Response
 */
function text(string $text, int $status = 200): Response
{
    return new Response()
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "text/plain"])
        ->withBody($text);
}

/**
 * Returns a Response instance configured to render an HTML view using the provided template and data.
 *
 * @param string $template
 * @param array  $data
 * @param int    $status
 * @return Response
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}

/**
 * In CLI mode, the data is dumped using var_dump. In a web environment, the output is wrapped in <pre> tags.
 *
 * @param mixed ...$data
 * @return void
 */
function dump(...$data): void
{
    if (Application::isCli()) {
        var_dump(...$data);
    } elseif (Application::isApi()) {
        echo json_encode($data);
    } else {
        echo "<pre>";
        var_dump(...$data);
        echo "</pre>";
    }

    die();
}
