<?php

namespace Essentio\Core;

use InvalidArgumentException;
use Stringable;

use function array_combine;
use function array_reverse;
use function array_shift;
use function explode;
use function http_build_query;
use function preg_replace;
use function rawurlencode;
use function str_starts_with;
use function substr;
use function trim;

class Router
{
    protected const LEAF = "\0LEAF_NODE";

    protected const PARAM = "\0PARAMETER";

    protected static array $middleware = [];

    protected static array $routes = [];

    protected static array $lookup = [];

    public static function middleware(callable $middleware): void
    {
        static::$middleware[] = $middleware;
    }

    public static function route(string $method, string $path, callable $handler): RouteInterface
    {
        $path = trim((string) preg_replace("#/+#", "/", $path), "/");
        $node = &static::$routes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                $node = &$node[static::PARAM];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        return $node[static::LEAF][$method] = new class ($path, $params, $handler) implements RouteInterface {
            public array $middleware = [];

            public function __construct(public string $path, public array $params, public $handler) {}

            public function name(string $name): static
            {
                Router::setName($name, $this->path);
                return $this;
            }

            public function middleware(callable $middleware): static
            {
                $this->middleware[] = $middleware;
                return $this;
            }
        };
    }

    public static function setName(string $name, string $path): void
    {
        static::$lookup[$name] = $path;
    }

    public static function makeUrlByName(string $name, array $params): string
    {
        if (!isset(static::$lookup[$name])) {
            throw new InvalidArgumentException("Route named [{$name}] not found.");
        }

        $url = static::$lookup[$name];

        foreach ($params as $key => $value) {
            $search = ":" . $key;
            if (str_contains($url, $search)) {
                $url = str_replace($search, rawurlencode((string) $value), $url);
                unset($params[$key]);
            }
        }

        if (str_contains($url, ":")) {
            throw new InvalidArgumentException("Missing parameter for route [{$name}].");
        }

        $query = empty($params) ? "" : "?" . http_build_query($params);
        return "/{$url}{$query}";
    }

    public static function dispatch(Request $request, Response $response): Response
    {
        $segments = explode("/", $request->path);
        $paramValues = [];
        $node = static::$routes;

        while ($segments) {
            $segment = array_shift($segments);

            if (isset($node[$segment])) {
                $node = $node[$segment];
                continue;
            }

            if (isset($node[static::PARAM])) {
                $paramValues[] = $segment;
                $node = $node[static::PARAM];
                continue;
            }

            throw HttpException::create(404);
        }

        if (!isset($node[static::LEAF])) {
            throw HttpException::create(404);
        }

        if (!isset($node[static::LEAF][$request->method])) {
            throw HttpException::create(405);
        }

        $route = $node[static::LEAF][$request->method];
        $request->parameters = array_combine($route->params, $paramValues);
        $handler = $route->handler;

        foreach (array_reverse($route->middleware) as $mw) {
            $handler = fn(Request $req) => $mw($req, $handler);
        }

        foreach (array_reverse(static::$middleware) as $mw) {
            $handler = fn(Request $req) => $mw($req, $handler);
        }

        $result = $handler($request);

        if ($result instanceof Response) {
            return $result;
        }

        if (($result instanceof Stringable || is_scalar($result)) && !empty(trim((string) $result))) {
            return $response->setBody($result);
        }

        throw HttpException::create(204);
    }
}
