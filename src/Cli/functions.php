<?php
declare(strict_types=1);

use Essentio\Cli\Argument;

/**
 * Get CLI argument by key.
 */
function arg(int|string $key): mixed
{
    return app(Argument::class)->get($key);
}

/**
 * Register and execute a CLI command.
 */
function command(string $name, callable $handle): void
{
    if (($argument = app(Argument::class))->command === $name) {
        exit(is_int($result = $handle($argument)) ? $result : 0);
    }
}
