<?php

namespace Essentio\Core;

use InvalidArgumentException;
use Override;
use Stringable;

/**
 * @api
 */
final class Router
{
    protected const LEAF = "\0LEAF_NODE";

    protected const PARAM = "\0PARAMETER";

    protected static array $middleware = [];

    protected static string $prefix = "";

    protected static array $routes = [];

    protected static array $lookup = [];

    /**
     * Register middleware for current route scope.
     *
     * @param callable(Request, callable(Request): Response): Response $middleware
     * @return void
     */
    public static function middleware(callable $middleware): void
    {
        static::$middleware[] = $middleware;
    }

    /**
     * Group routes under a common prefix and middleware.
     *
     * @param string $prefix
     * @param callable(): void $group
     * @return void
     */
    public static function group(string $prefix, callable $group): void
    {
        [$oldPrefix, $oldMiddleware] = [static::$prefix, static::$middleware];
        static::$prefix .= $prefix;
        $group();
        [static::$prefix, static::$middleware] = [$oldPrefix, $oldMiddleware];
    }

    /**
     * Register a route handler for a method and path.
     *
     * @param string $method
     * @param string $path
     * @param callable(Request): mixed $handler
     * @return RouteInterface
     */
    public static function route(string $method, string $path, callable $handler): RouteInterface
    {
        $path = trim((string) preg_replace("#/+#", "/", static::$prefix . $path), "/");
        /** @psalm-suppress UnsupportedPropertyReferenceUsage */
        $node = &static::$routes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                /** @psalm-suppress UnsupportedPropertyReferenceUsage */
                $node = &$node[static::PARAM];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        $middleware = static::$middleware;

        return $node[static::LEAF][$method] = new class ($path, $params, $middleware, $handler) implements RouteInterface {
            public function __construct(public string $path, public array $params, public array $middleware, public $handler) {}

            #[Override]
            public function name(string $name): static
            {
                Router::setName($name, $this->path);
                return $this;
            }

            #[Override]
            public function middleware(callable $middleware): static
            {
                $this->middleware[] = $middleware;
                return $this;
            }
        };
    }

    /**
     * Assign a name to a route path.
     *
     * @param string $name
     * @param string $path
     * @return void
     */
    public static function setName(string $name, string $path): void
    {
        static::$lookup[$name] = $path;
    }

    /**
     * Generate a URL for a named route with parameters.
     *
     * @param string $name
     * @param array<string, scalar> $params
     * @return string
     */
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

    /**
     * Match a request and execute the route handler.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
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
