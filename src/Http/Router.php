<?php

declare(strict_types=1);

namespace Essentio\Http;

use Stringable;

use function array_combine;
use function array_reverse;
use function array_shift;
use function explode;
use function is_scalar;
use function mb_strlen;
use function preg_replace;
use function str_starts_with;
use function substr;
use function trim;

/**
 * @api
 */
class Router
{
    protected const LEAF = "__leafnode__";

    protected const PARAM = "__parameter__";

    /** @var list<callable> */
    protected array $middleware = [];

    protected string $prefix = "";

    protected array $routes = [];

    protected array $lookup = [];

    /**
     * Register middleware for current route scope.
     *
     * @param callable(Request, callable(Request): Response): Response $middleware
     */
    public function middleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Group routes under a common prefix and middleware.
     *
     * @param callable(): void $group
     */
    public function group(string $prefix, callable $group): void
    {
        [$oldPrefix, $oldMiddleware] = [$this->prefix, $this->middleware];
        $this->prefix .= $prefix;
        $group();
        [$this->prefix, $this->middleware] = [$oldPrefix, $oldMiddleware];
    }

    /**
     * Register a route handler for a method and path.
     *
     * @param callable(Request):mixed $handler
     * @param list<callable(Request,callable):Response> $middleware
     */
    public function route(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $path = trim((string) preg_replace("#/+#", "/", $this->prefix . $path), "/");
        /** @psalm-suppress UnsupportedPropertyReferenceUsage */
        $node = &$this->routes;
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

        $node[static::LEAF][$method] = [$params, array_merge($this->middleware, $middleware), $handler];
    }

    /**
     * Match a request and execute the route handler.
     */
    public function dispatch(Request $request, Response $response): Response
    {
        $segments = explode("/", $request->path);
        $paramValues = [];
        $node = $this->routes;

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

        $method = $request->method;
        if ($request->method === "HEAD" && !isset($node[self::LEAF]["HEAD"]) && isset($node[self::LEAF]["GET"])) {
            $method = "GET";
        }

        if (!isset($node[static::LEAF])) {
            throw HttpException::create(404);
        }

        if (!isset($node[static::LEAF][$method])) {
            throw HttpException::create(405);
        }

        [$params, $middleware, $handler] = $node[static::LEAF][$method];
        $request->parameters = array_combine($params, $paramValues);

        foreach (array_reverse($middleware) as $mw) {
            $handler = fn(Request $request) => $mw($request, $handler);
        }

        $result = $handler($request);

        if ($result instanceof Response) {
            if ($request->method === "HEAD") {
                return $result->setBody("");
            }

            return $result;
        }

        if ($result instanceof Stringable || is_scalar($result)) {
            if ($request->method === "HEAD") {
                return $response->addHeaders(["Content-Length" => (string) mb_strlen((string) $result)]);
            }

            return $response->setBody((string) $result);
        }

        throw HttpException::create(204);
    }
}
