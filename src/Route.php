<?php

namespace Essentio\Core;

class Route
{
    public array $middleware = [];

    public function __construct(protected string $path, public readonly array $params, public $handler, protected $setName) {}

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
}
