<?php

namespace Essentio\Core;

use InvalidArgumentException;

class Router
{
    protected const LEAF = "\0LEAF_NODE";

    protected const PARAM = "\0PARAMETER";

    public function __construct(
        protected array $middleware = [],
        protected array $routes = [],
        protected array $named = []
    ) {}

    public function middleware(callable $middleware): static
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function add(
        string $method,
        string $path,
        callable $handler,
        ?string $name = null,
        array $middleware = []
    ): static {
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

        if ($name) {
            $this->named[$name] = $path;
        }

        $node[static::LEAF][$method] = [$params, $middleware, $handler];
        return $this;
    }

    public function getUrl(string $name, array $params): string
    {
        if (!isset($this->named[$name])) {
            throw new InvalidArgumentException("Route named '{$name}' not found.");
        }

        $consumed = [];
        $url = preg_replace_callback(
            "#:([\w]+)#",
            function ($matches) use ($params, &$consumed, $name) {
                if (!isset($params[$matches[1]])) {
                    throw new InvalidArgumentException("Missing parameter '{$matches[1]}' for route '{$name}'.");
                }

                $consumed[$matches[1]] = true;
                return rawurlencode((string) $params[$matches[1]]);
            },
            $this->named[$name]
        );

        $extra = array_diff_key($params, array_flip($consumed));
        $query = $extra ? "?" . http_build_query($extra) : "";

        return "/" . ltrim($url, "/") . $query;
    }

    public function dispatch(Request $req, Response $res): Response
    {
        [$values, $routes] = $this->match($this->routes, explode("/", $req->path)) ?? throw HttpException::create(404);

        if (!isset($routes[$req->method])) {
            throw HttpException::create(405);
        }

        [$params, $middleware, $handler] = $routes[$req->method];
        $req->parameters = array_combine($params, $values);

        foreach (array_reverse($this->middleware) as $mw) {
            $handler = fn($req, $res) => $mw($req, $res, $handler);
        }

        foreach (array_reverse($middleware) as $mw) {
            $handler = fn($req, $res) => $mw($req, $res, $handler);
        }

        $result = $handler($req, $res);
        return $result instanceof Response ? $result : $res;
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
