<?php

declare(strict_types=1);
use Essentio\Http\Request;
use Essentio\Http\Response;
use Essentio\Http\Router;

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
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function get(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("GET", $path, $handler, $middleware);
}

/**
 * Register a POST route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function post(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("POST", $path, $handler, $middleware);
}

/**
 * Register a PUT route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function put(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("PUT", $path, $handler, $middleware);
}

/**
 * Register a PATCH route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function patch(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("PATCH", $path, $handler, $middleware);
}

/**
 * Register a DELETE route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function delete(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("DELETE", $path, $handler, $middleware);
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
 * Convert an array or object to an xml string.
 */
function data_to_xml(array|object $data, string $rootElement = "root", ?SimpleXMLElement $xml = null): string
{
    if (!$xml instanceof SimpleXMLElement) {
        $xml = new SimpleXMLElement(sprintf('<?xml version="1.0"?><%s></%s>', $rootElement, $rootElement));
    }

    foreach ((array) $data as $key => $value) {
        if (is_numeric($key)) {
            $key = "item";
        }

        if (is_array($value) || is_object($value)) {
            data_to_xml($value, $key, $xml->addChild($key));
        } else {
            $xml->addChild($key, htmlspecialchars((string) $value));
        }
    }

    return (string) ($xml->asXML() ?: "");
}

/**
 * Create an XML response.
 */
function xml(array|object $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/xml"])
        ->setBody(data_to_xml($data));
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
 * Create a plain text response.
 */
function text(string $text, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}
