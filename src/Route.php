<?php

namespace Essentio\Core;

class Route
{
    protected array $middleware = [];

    public function __construct(
        protected string $path,
        protected array $params,
        protected $handler,
        protected $setName
    ) {}

    public function name(string $name): static
    {
        ($this->setName)($name, $this->path);
        return $this;
    }

    public function middleware(callable $middleware): static
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function getInternals(): object
    {
        return (object) [
            "params" => $this->params,
            "handler" => $this->handler,
            "middleware" => $this->middleware,
        ];
    }
}
