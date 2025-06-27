<?php

namespace Essentio\Core;

interface RouteInterface
{
    public function name(string $name): static;

    public function middleware(callable $middleware): static;
}
