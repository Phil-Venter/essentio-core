<?php

use Essentio\Core\Application;
use Essentio\Core\Argument;
use Essentio\Core\Environment;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\Router;
use Essentio\Core\Template;

/**
 * If no identifier is provided, returns the container instance.
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return ($id is class-string<T> ? T : object)
 */
function app(?string $id = null): object
{
    return $id ? Application::$container->resolve($id) : Application::$container;
}

/**
 * Attempt to resolve path from base as passed to Application factory.
 *
 * @param string $path
 * @return string
 */
function base_path(string $path = ""): string
{
    return Application::fromBase($path);
}

/**
 * This function fetches an environment variable from the Environment instance.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    return app(Environment::class)->get($key, $default);
}

/**
 * This function binds a service to the container using the specified identifier and factory.
 *
 * @param string $id
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
 * @param int|string $key
 * @param mixed      $default
 * @return string|array|null
 */
function arg(int|string $key, mixed $default = null): string|array|null
{
    return app(Argument::class)->get($key, $default);
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
    if (Application::$isWeb) {
        return;
    }

    $argv = app(Argument::class);

    if ($argv->command !== $name) {
        return;
    }

    $result = $handle($argv);

    exit(is_int($result) ? $result : 0);
}

/**
 * Fetches a value from the current Request instance using the specified key.
 *
 * @param array|string $key
 * @param mixed        $default
 * @return mixed
 */
function request(array|string $key, mixed $default = null): mixed
{
    return app(Request::class)->get($key, $default);
}

/**
 * Fetches a value from the current Request instance body using the specified key.
 *
 * @param array|string $key
 * @param mixed        $default
 * @return mixed
 */
function input(array|string $key, mixed $default = null): mixed
{
    return app(Request::class)->input($key, $default);
}

/**
 * Add middleware that will be applied globally.
 *
 * @param callable $middleware
 * @return void
 */
function middleware(callable $middleware): void
{
    if (!Application::$isWeb) {
        return;
    }

    app(Router::class)->use($middleware);
}

/**
 * Groups routes under a shared prefix and middleware stack for scoped handling.
 *
 * @param string $prefix
 * @param callable $handle
 * @param array $middleware
 * @return void
 */
function group(string $prefix, callable $handle, array $middleware = []): void
{
    if (!Application::$isWeb) {
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
    if (!Application::$isWeb) {
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
    if (!Application::$isWeb) {
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
    if (!Application::$isWeb) {
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
    if (!Application::$isWeb) {
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
    if (!Application::$isWeb) {
        return;
    }

    app(Router::class)->add("DELETE", $path, $handle, $middleware);
}

/**
 * Sets flash data if a value is provided, or retrieves and removes flash data for the given key.
 *
 * @param array|string $key
 * @param mixed        $value
 * @return mixed
 */
function flash(array|string $key, mixed $value = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if (is_array($key)) {
        if (array_is_list($key)) {
            $result = [];

            foreach ($key as $k) {
                $result[$k] = flash($k);
            }

            return $result;
        }

        foreach ($key as $k => $v) {
            flash($k, $v);
        }

        return null;
    }

    if (func_num_args() === 2) {
        return $_SESSION["_flash"][$key] = $value;
    }

    $val = $_SESSION["_flash"][$key] ?? null;
    unset($_SESSION["_flash"][$key]);
    return $val;
}

/**
 * Sets session data if a value is provided, or retrieves session data for the given key.
 *
 * @param array|string $key
 * @param mixed        $value
 * @return mixed
 */
function session(array|string $key, mixed $value = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if (is_array($key)) {
        if (array_is_list($key)) {
            $result = [];

            foreach ($key as $k) {
                $result[$k] = session($k);
            }

            return $result;
        }

        foreach ($key as $k => $v) {
            session($k, $v);
        }

        return null;
    }

    if (func_num_args() === 2) {
        return $_SESSION[$key] = $value;
    }

    return $_SESSION[$key] ?? null;
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
    return (new Template($template))->render($data);
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
    return (new Response())->withStatus($status)->withHeaders(["Location" => $uri]);
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
    return (new Response())
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
    return (new Response())
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
    return (new Response())
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
 * Logs a message at a given log level to a file specified in the configuration.
 *
 * @param string $level
 * @param string $message
 * @return void
 */
function logger(string $level, string $message): void
{
    $level = strtoupper($level);
    $file = base_path(env(sprintf("%s_LOG_FILE", $level), "app.log"));
    $line = sprintf("[%s] [%s]: %s" . PHP_EOL, date("Y-m-d H:i:s"), $level, $message);
    file_put_contents($file, $line, FILE_APPEND);
}

/**
 * In CLI mode, the data is dumped using var_dump. In a web environment, the output is wrapped in <pre> tags.
 *
 * @param mixed ...$data
 * @return void
 */
function dump(...$data): void
{
    if (!Application::$isWeb) {
        var_dump(...$data);
        return;
    }

    echo "<pre>";
    var_dump(...$data);
    echo "</pre>";
}

/**
 * This function allows you to perform an operation on the value and then return the original value.
 * [Keeping it around to see if this is framework or implementation detail.]
 *
 * @param mixed    $value
 * @param callable $callback
 * @return mixed
 */
function tap(mixed $value, callable $callback): mixed
{
    $callback($value);
    return $value;
}

/**
 * Evaluates the provided condition, and if it is true, throws the specified exception.
 * [Keeping it around to see if this is framework or implementation detail.]
 *
 * @param bool $condition
 * @param Throwable $e
 * @return void
 * @throws Throwable
 */
function throw_if(bool $condition, Throwable $e): void
{
    if ($condition) {
        throw $e;
    }
}

/**
 * If the value is callable, it executes the callback and returns its result. Otherwise, it returns the value as is.
 * [Keeping it around to see if this is framework or implementation detail.]
 *
 * @param mixed $value
 * @return mixed
 */
function value(mixed $value): mixed
{
    if (is_callable($value)) {
        return call_user_func($value);
    }

    return $value;
}

/**
 * If the condition is false, the function returns null.
 * If the condition is true and the callback is callable, it executes the callback and returns its result; otherwise, it returns the provided value directly.
 * [Keeping it around to see if this is framework or implementation detail.]
 *
 * @param bool  $condition
 * @param mixed $callback
 * @return mixed
 */
function when(bool $condition, mixed $value): mixed
{
    if (!$condition) {
        return null;
    }

    return value($value);
}
