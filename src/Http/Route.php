<?php

declare(strict_types=1);

namespace Essentio\Http;

/**
 * @api
 */
class Route
{
    /**
     * @var callable
     */
    public $handler;

    protected $setName;

    /**
     * @param list<string> $params
     * @param list<callable> $middleware
     */
    public function __construct(
        public readonly string $path,
        public readonly array $params,
        public array $middleware,
        callable $handler,
        callable $setName
    ) {
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
