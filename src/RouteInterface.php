<?php

namespace Essentio\Core;

/**
 * @api
 */
interface RouteInterface
{
    public function name(string $name): static;

    public function middleware(callable $middleware): static;
}
