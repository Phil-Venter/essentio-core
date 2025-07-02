<?php

namespace Essentio\Core;

/**
 * @api
 */
interface RouteInterface
{
    public function name(string $name): static;

    /**
     * @param callable(Request,callable):Response $middleware
     */
    public function middleware(callable $middleware): static;
}
