<?php

namespace Essentio\Core;

use InvalidArgumentException;
use Stringable;

use function array_combine;
use function array_key_exists;
use function array_reverse;
use function array_shift;
use function explode;
use function http_build_query;
use function ltrim;
use function preg_replace;
use function preg_replace_callback;
use function rawurlencode;
use function str_starts_with;
use function substr;
use function trim;

class Router
{
    protected const LEAF = "\0LEAF_NODE";

    protected const PARAM = "\0PARAMETER";

    protected array $middleware = [];

    protected array $routes = [];

    protected array $lookup = [];

    public function middleware(callable $middleware): static
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function add(string $method, string $path, callable $handler): Route
    {
        $path = trim((string) preg_replace("#/+#", "/", $path), "/");
        $node = &$this->routes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                $node = &$node[static::PARAM];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        return $node[static::LEAF][$method] = new Route($path, $params, $handler, $this->setName(...));
    }

    public function makeUrlByName(string $name, array $params): string
    {
        if (!isset($this->lookup[$name])) {
            throw new InvalidArgumentException("Route named '{$name}' not found.");
        }

        $url = preg_replace_callback(
            "#:([\w]+)#",
            function ($matches) use (&$params, $name) {
                if (!array_key_exists($key = $matches[1], $params)) {
                    throw new InvalidArgumentException("Missing parameter '{$key}' for route '{$name}'.");
                }

                $value = $params[$key];
                unset($params[$key]);
                return rawurlencode((string) $value);
            },
            $this->lookup[$name]
        );

        return "/" . ltrim($url, "/") . (empty($params) ? "" : "?" . http_build_query($params));
    }

    public function dispatch(Request $request, Response $response): Response
    {
        [$values, $routes] = $this->match($this->routes, explode("/", $request->path)) ?? throw HttpException::create(404);

        if (!isset($routes[$request->method])) {
            throw HttpException::create(405);
        }

        $request->parameters = array_combine($routes[$request->method]->params, $values);
        $handler = $routes[$request->method]->handler;

        foreach (array_reverse($routes[$request->method]->middleware) as $mw) {
            $handler = fn(Request $req) => $mw($req, $handler);
        }

        foreach (array_reverse($this->middleware) as $mw) {
            $handler = fn(Request $req) => $mw($req, $handler);
        }

        if (($result = $handler($request)) instanceof Response) {
            return $result;
        }

        if (($result instanceof Stringable || is_scalar($result)) && !empty(trim((string) $result))) {
            return $response->setBody($result);
        }

        throw HttpException::create(204);
    }

    protected function setName(string $name, string $path): void
    {
        $this->lookup[$name] = $path;
    }

    protected function match(array $node, array $segments, array $params = []): ?array
    {
        if (!$segments) {
            return $node[static::LEAF] ?? null ? [$params, $node[static::LEAF]] : null;
        }

        $segment = array_shift($segments);

        if (isset($node[$segment]) && ($found = $this->match($node[$segment], $segments, $params))) {
            return $found;
        }

        if (isset($node[static::PARAM])) {
            return $this->match($node[static::PARAM], $segments, [...$params, $segment]);
        }

        return null;
    }
}
