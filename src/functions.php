<?php

/**
 * This function fetches an environment variable from the Environment instance.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    return app(Essentio\Core\Environment::class)->get($key, $default);
}

/**
 * If no identifier is provided, returns the container instance.
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return ($id is class-string<T> ? T : object)
 */
function app(?string $id = null): object
{
    return $id
        ? Essentio\Core\Application::$container->get($id)
        : Essentio\Core\Application::$container;
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
 * Executes the provided command handler if the current command matches the specified name.
 *
 * @param string $name
 * @param callable $handle
 * @return void
 */
function command(string $name, callable $handle): void
{
    if (Essentio\Core\Application::$isWeb) {
        return;
    }

    $argv = app(Essentio\Core\Argument::class);

    if ($argv->command !== $name) {
        return;
    }

    $result = $handle($argv);

    exit(is_int($result) ? $result : 0);
}

/**
 * Fetches a value from the current Request instance using the specified key.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function request(string $key, mixed $default = null): mixed
{
    return app(Essentio\Core\Request::class)->get($key, $default);
}

/**
 * Registers a route for the given HTTP method and path with an associated handler and optional middleware.
 * This function is only applicable in a web environment.
 *
 * @param string         $method
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function route(string $method, string $path, callable $handle, array $middleware = []): void
{
    if (!Essentio\Core\Application::$isWeb) {
        return;
    }

    app(Essentio\Core\Router::class)->route($method, $path, $handle, $middleware);
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
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if ($value !== null) {
        return $_SESSION['_flash'][$key] = $value;
    }

    $val = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $val;
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
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if ($value !== null) {
        return $_SESSION[$key] = $value;
    }

    return $_SESSION[$key] ?? null;
}

/**
 * This function attempts to resolve the template as a file based on the Essentio\Core\Application's base path.
 * If the resolved path is a file, it extracts the provided data, includes the file, and returns the output.
 * Otherwise, if the template string contains placeholder patterns (using curly braces),
 * it processes the string using regex callbacks to replace placeholders with data values.
 *
 * @param string $template
 * @param array  $data
 * @return string
 */
function render(string $template, array $data = []): string
{
    if ($path = Essentio\Core\Application::fromBase($template)) {
        extract($data);
        ob_start();
        include $path;
        return ob_get_clean();
    }

    if (!str_contains($template, '{{') || !str_contains($template, '}}')) {
        return '';
    }

    return preg_replace_callback_array([
        '/\{\{\{\s*(.*?)\s*\}\}\}/' => fn($matches) => $data[$matches[1]] ?? '',
        '/\{\{\s*(.*?)\s*\}\}/' => fn($matches) => isset($data[$matches[1]])
            ? htmlentities($data[$matches[1]])
            : '',
    ], $template);
}

/**
 * Returns a Response instance configured to redirect to the specified URI with the given status code.
 *
 * @param string $uri
 * @param int    $status
 * @return Essentio\Core\Response
 */
function redirect(string $uri, int $status = 302): Essentio\Core\Response
{
    return (new Essentio\Core\Response)
        ->withStatus($status)
        ->withHeaders(['Location' => $uri]);
}

/**
 * Returns a Response instance configured to send JSON data with the specified status code.
 *
 * @param mixed $data
 * @param int   $status
 * @return Essentio\Core\Response
 */
function json(mixed $data, int $status = 200): Essentio\Core\Response
{
    return (new Essentio\Core\Response)
        ->withStatus($status)
        ->withHeaders(['Content-Type' => 'Essentio\Core\Application/json'])
        ->withBody(json_encode($data));
}

/**
 * Returns a Response instance configured to send plain text with the specified status code.
 *
 * @param string $text
 * @param int    $status
 * @return Essentio\Core\Response
 */
function text(string $text, int $status = 200): Essentio\Core\Response
{
    return (new Essentio\Core\Response)
        ->withStatus($status)
        ->withHeaders(['Content-Type' => 'text/plain'])
        ->withBody($text);
}

/**
 * Returns a Response instance configured to render an HTML view using the provided template and data.
 *
 * @param string $template
 * @param array  $data
 * @param int    $status
 * @return Essentio\Core\Response
 */
function view(string $template, array $data = [], int $status = 200): Essentio\Core\Response
{
    return (new Essentio\Core\Response)
        ->withStatus($status)
        ->withHeaders(['Content-Type' => 'text/html'])
        ->withBody(render($template, $data));
}

/**
 * This function logs a message using PHP's error_log function.
 *
 * @param string $format
 * @param mixed ...$values
 * @return void
 */
function log_cli(string $format, ...$values): void
{
    error_log(sprintf($format, ...$values));
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
    $file = config(sprintf('log.%s', strtolower($level)), 'app.log');
    $file = Essentio\Core\Application::fromBase(dirname($file)) . '/' . basename($file);

    if (!is_file($file)) {
        touch($file);
    }

    $msg = sprintf("[%s] [%s]: %s\n", date('Y-m-d H:i:s'), strtoupper($level), $message);

    file_put_contents($file, $msg, FILE_APPEND);
}

/**
 * In CLI mode, the data is dumped using var_dump.
 * In a web environment, the output is wrapped in <pre> tags.
 *
 * @param mixed ...$data
 * @return void
 */
function dump(...$data): void
{
    if (!Essentio\Core\Application::$isWeb) {
        var_dump(...$data);
        return;
    }

    echo '<pre>';
    var_dump(...$data);
    echo '</pre>';
}

/**
 * Evaluates the provided condition, and if it is true, throws the specified exception.
 *
 * @param bool $condition
 * @param \Throwable $e
 * @return void
 * @throws \Throwable
 */
function throw_if(bool $condition, Throwable $e): void
{
    if ($condition) {
        throw $e;
    }
}

/**
 * This function attempts to execute the provided callable. If any exception or error is thrown,
 * it logs the error message using the error log mechanism and returns the default value.
 *
 * @param callable $callback
 * @param mixed    $default
 * @return mixed
 */
function safe(callable $callback, mixed $default = null): mixed
{
    try {
        return $callback();
    } catch (\Throwable $e) {
        logger('error', $e->getMessage());
        return $default;
    }
}

/**
 * This function allows you to perform an operation on the value and then
 * return the original value.
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
 * If the value is callable, it executes the callback and returns its result.
 * Otherwise, it returns the value as is.
 *
 * @param mixed $value
 * @return mixed
 */
function value(mixed $value): mixed
{
    if (is_callable($value)) {
        return $value();
    }

    return $value;
}

/**
 * If the condition is false, the function returns null.
 * If the condition is true and the callback is callable, it executes the callback and returns its result;
 * otherwise, it returns the provided value directly.
 *
 * @param bool  $condition
 * @param mixed $callback
 * @return mixed
 */
function when(bool $condition, mixed $callback): mixed
{
    if (!$condition) {
        return null;
    }

    if (is_callable($callback)) {
        return $callback();
    }

    return $callback;
}
