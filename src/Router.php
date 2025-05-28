<?php

namespace Essentio\Core;

use function array_combine;
use function array_merge;
use function array_reverse;
use function array_shift;
use function call_user_func;
use function explode;
use function preg_replace;
use function str_starts_with;
use function substr;
use function trim;

class Router
{
    protected const LEAFNODE = "\0LEAF_NODE";

    protected const WILDCARD = "\0WILDCARD";

    /** @var list<callable> */
    protected array $globalMiddleware = [];

    /** @var string */
    protected string $prefix = "";

    /** @var list<callable> */
    protected array $middleware = [];

    /** @var array<string, array{list<callable>, callable}> */
    protected array $routes = [];

    /**
     * Add middleware that will be applied globally
     *
     * @param callable $middleware
     * @return static
     */
    public function use(callable $middleware): static
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Groups routes under a shared prefix and middleware stack for scoped handling.
     *
     * @param string         $prefix
     * @param callable       $handle
     * @param list<callable> $middleware
     * @return static
     */
    public function group(string $prefix, callable $handle, array $middleware = []): static
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->middleware;

        $this->prefix .= $prefix;
        $this->middleware = array_merge($this->middleware, $middleware);

        $handle($this);

        $this->prefix = $previousPrefix;
        $this->middleware = $previousMiddleware;

        return $this;
    }

    /**
     * Registers a route with the router.
     *
     * @param string         $method
     * @param string         $path
     * @param callable       $handle
     * @param list<callable> $middleware
     * @return static
     */
    public function add(string $method, string $path, callable $handle, array $middleware = []): static
    {
        $path = trim((string) preg_replace("/\/+/", "/", $this->prefix . $path), "/");
        $node = &$this->routes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                $node = &$node[static::WILDCARD];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        $middlewares = array_merge($this->globalMiddleware, $this->middleware, $middleware);
        $node[static::LEAFNODE][$method] = [$params, $middlewares, $handle];
        return $this;
    }

    /**
     * Dispatches the incoming HTTP request and executes the corresponding route.
     *
     * @param Request $request
     * @return Response
     * @throws HttpException
     */
    public function dispatch(Request $request): Response
    {
        $result = $this->search($this->routes, explode("/", $request->path));

        if ($result === null) {
            throw HttpException::new(404);
        }

        [$values, $methods] = $result;

        if (!isset($methods[$request->method])) {
            throw HttpException::new(405);
        }

        [$params, $middleware, $handle] = $methods[$request->method];

        $req = $request->setParameters(array_combine($params, $values));
        return $this->call($req, $middleware, $handle);
    }

    /**
     * Recursively searches the route trie for a matching route.
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
     * @param Request  $request
     * @param array    $middleware
     * @param callable $handle
     * @return Response
     */
    protected function call(Request $request, array $middleware, callable $handle): Response
    {
        $pipeline = $handle;

        foreach (array_reverse($middleware) as $m) {
            $pipeline = fn($req, $res): mixed => call_user_func($m, $req, $res, $pipeline);
        }

        $response = new Response();
        $result = call_user_func($pipeline, $request, $response);

        if ($result instanceof Response) {
            return $result;
        }

        return $response;
    }
}
