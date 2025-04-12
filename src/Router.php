<?php

namespace Essentio\Core;

use function array_reverse;
use function array_shift;
use function call_user_func;
use function explode;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function substr;
use function trim;

/**
 * Handles the registration and execution of routes for HTTP requests.
 * Supports both static and dynamic routes with middleware pipelines.
 */
class Router
{
    protected const LEAFNODE = "\0L";
    protected const WILDCARD = "\0W";

    protected string $currentPrefix = "";
    protected array $globalMiddleware = [];
    protected array $currentMiddleware = [];

    /** @var array<string, array{array<callable>, callable}> */
    protected array $staticRoutes = [];

    /** @var array<string, array{array<callable>, callable}> */
    protected array $dynamicRoutes = [];

    /**
     * @param callable $middleware
     * @return static
     */
    public function use(callable $middleware): static
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * @param string $prefix
     * @param callable $handle
     * @param list<callable> $middleware
     * @return static
     */
    public function group(string $prefix, callable $handle, array $middleware = []): static
    {
        $previousPrefix = $this->currentPrefix;
        $previousMiddleware = $this->currentMiddleware;

        $this->currentPrefix .= $prefix;
        $this->currentMiddleware = array_merge($this->currentMiddleware, $middleware);

        $handle($this);

        $this->currentPrefix = $previousPrefix;
        $this->currentMiddleware = $previousMiddleware;

        return $this;
    }

    /**
     * Registers a route with the router.
     *
     * This method accepts an HTTP method, a route path, a handler callable,
     * and an optional array of middleware. It processes the path to support
     * dynamic segments and stores the route in the appropriate routes array.
     *
     * @param string         $method
     * @param string         $path
     * @param callable       $handle
     * @param list<callable> $middleware
     * @return static
     */
    public function add(string $method, string $path, callable $handle, array $middleware = []): static
    {
        $path = trim(preg_replace("/\/+/", "/", $this->currentPrefix . $path), "/");
        $allMiddleware = array_merge($this->globalMiddleware, $this->currentMiddleware, $middleware);

        if (!str_contains($path, ":")) {
            $this->staticRoutes[$path][$method] = [$allMiddleware, $handle];
            return $this;
        }

        $node = &$this->dynamicRoutes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                $node = &$node[static::WILDCARD];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        $node[static::LEAFNODE][$method] = [$params, $allMiddleware, $handle];
        return $this;
    }

    /**
     * Dispatches the incoming HTTP request and executes the corresponding route.
     *
     * This method first checks for a matching static route based on the request path and HTTP method.
     * If a static match is found, its middleware pipeline and handler are executed.
     * If no static route is found, it searches for a matching dynamic route using an internal trie structure.
     *
     * For dynamic routes, if a match is found, the extracted parameters are merged into the request,
     * and the associated middleware and handler are executed.
     *
     * If no matching route is found, a HttpException with a 404 status code is thrown.
     * If a matching route is found but does not support the request method, a HttpException with a 405 status code is thrown.
     *
     * @param Request $request
     * @return Response
     *
     * @throws HttpException
     */
    public function dispatch(Request $request): Response
    {
        if (isset($this->staticRoutes[$request->path][$request->method])) {
            [$middleware, $handle] = $this->staticRoutes[$request->path][$request->method];
            return $this->call($request, $middleware, $handle);
        }

        $result = $this->search($this->dynamicRoutes, explode("/", $request->path));

        if ($result === null) {
            throw new HttpException("Route not found", 404);
        }

        [$values, $methods] = $result;

        if (!isset($methods[$request->method])) {
            throw new HttpException("Method not allowed", 405);
        }

        [$params, $middleware, $handle] = $methods[$request->method];

        $req = $request->setParameters(array_combine($params, $values));
        return $this->call($req, $middleware, $handle);
    }

    /**
     * Recursively searches the route trie for a matching route.
     *
     * Traverses the trie using the provided path segments to determine if a route exists.
     * If a segment matches a literal or wildcard, the function recursively proceeds.
     * When all segments have been processed, if a leaf node is found, it returns an array
     * containing the dynamic parameters extracted from the path and the associated methods mapping.
     *
     * @param array $trie
     * @param array $segments
     * @param array $params
     * @return array|null
     */
    protected function search(array $trie, array $segments, array $params = []): ?array
    {
        if (empty($segments)) {
            return isset($trie[static::LEAFNODE]) ? [$params, $trie[static::LEAFNODE]] : null;
        }

        $segment = array_shift($segments);

        if (isset($trie[$segment])) {
            if ($result = $this->search($trie[$segment], $segments, $params)) {
                return $result;
            }
        }

        if (isset($trie[static::WILDCARD])) {
            $params[] = $segment;

            if ($result = $this->search($trie[static::WILDCARD], $segments, $params)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Executes the route handler within a middleware pipeline.
     *
     * This method constructs a pipeline where each middleware wraps around the handler.
     * The request and a new Response instance are passed through the pipeline.
     * The final Response is then returned.
     *
     * @param Request  $request
     * @param array    $middleware
     * @param callable $handle
     * @return Response
     */
    protected function call(Request $request, array $middleware, callable $handle): Response
    {
        $pipeline = $handle;

        foreach (array_reverse($middleware) as $m) {
            $pipeline = fn($req, $res) => call_user_func($m, $req, $res, $pipeline);
        }

        $response = new Response();
        $result = call_user_func($pipeline, $request, $response);

        if ($result instanceof Response) {
            return $result;
        }

        return $response;
    }
}
