<?php

namespace Essentio\Core;

class Router
{
    protected const LEAF = "\0LEAF_NODE";

    protected const PARAM = "\0PARAMETER";

    protected array $middleware = [];

    protected array $routes = [];

    public function middleware(callable $middleware): static
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function add(string $method, string $path, callable $handler, array $middleware = []): static
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

        $node[static::LEAF][$method] = [$params, $middleware, $handler];
        return $this;
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
