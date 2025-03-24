<?php

namespace Essentio\Core;

/**
 * Handles the registration and execution of routes for HTTP requests.
 * Supports both static and dynamic routes with middleware pipelines.
 */
class Router
{
    /** @var array<string, array{array<callable>, callable}> */
    protected array $staticRoutes = [];

    /** @var array<string, array{array<callable>, callable}> */
    protected array $dynamicRoutes = [];

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
    public function route(string $method, string $path, callable $handle, array $middleware = []): static
    {
        $path = '/' . \trim(\preg_replace(['/\/+/', '/\{([^\/]+)\}/'], ['/', '(?P<$1>[^\/]+)'], $path), '/');

        if (\str_contains($path, '(?P<')) {
            $this->dynamicRoutes[$path][$method] = [$middleware, $handle];
        } else {
            $this->staticRoutes[$path][$method] = [$middleware, $handle];
        }

        return $this;
    }

    /**
     * Executes the matching route for the given request.
     *
     * Attempts to match the request path and method with registered static or dynamic routes.
     * If a matching route is found, its middleware pipeline and handler are executed.
     * Returns a 404 response if no route is matched, or a 500 response if an error occurs.
     *
     * @param Request $request
     * @return Response
     */
    public function run(Request $request): Response
    {
        if (isset($this->staticRoutes[$request->path][$request->method])) {
            [$middleware, $handle] = $this->staticRoutes[$request->path][$request->method];
            return $this->call($request, $middleware, $handle);
        }

        foreach ($this->dynamicRoutes as $pattern => $node) {
            if (!\preg_match($pattern, $request->path, $matches)) {
                continue;
            }

            if (!isset($node[$request->method])) {
                continue;
            }

            $parameters = \array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            [$middleware, $handle] = $node[$request->method];

            return $this->call($request->withParameters($parameters), $middleware, $handle);
        }

        return (new Response)->withStatus(404);
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

        foreach (\array_reverse($middleware) as $m) {
            $pipeline = fn($req, $res) => $m($req, $res, $pipeline);
        }

        $response = new Response();
        $result = $pipeline($request, $response);

        if ($result instanceof Response) {
            return $result;
        }

        return $response;
    }
}
