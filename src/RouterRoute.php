<?php

namespace Essentio\Core;

/**
 * @api
 */
class RouterRoute
{
    /**
     * @var callable
     */
    public $handler;

    protected $setName;

    /**
     * @param array<string,mixed> $params
     * @param list<callable> $middleware
     */
    public function __construct(public readonly string $path, public readonly array $params, public protected(set) array $middleware, callable $handler, callable $setName)
    {
        $this->handler = $handler;
        $this->setName = $setName;
    }

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
