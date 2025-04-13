<?php

namespace Essentio\Core;

use function array_merge;
use function array_shift;
use function explode;
use function is_int;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Parses and holds command-line arguments including the command,
 * named parameters, and positional parameters.
 */
class Argument
{
    /** @var string */
    public protected(set) string $command = '';

    /** @var array<string,mixed> */
    public protected(set) array $named = [];

    /** @var list<mixed> */
    public protected(set) array $positional = [];

    /**
     * Initializes and parses the command-line arguments.
     *
     * This method extracts the command, named, and positional arguments
     * from the $_SERVER['argv'] array. It supports both long (--name)
     * and short (-n) argument formats.
     *
     * @return static
     */
    public static function init(?array $argv = null): static
    {
        $argv ??= $_SERVER['argv'] ?? [];

        $that = new static;

        if (empty($argv)) {
            return $that;
        }

        array_shift($argv);

        while ($arg = array_shift($argv)) {
            if ($arg === '--') {
                $that->positional = array_merge($that->positional, $argv);
                break;
            }

            if (str_starts_with($arg, '--')) {
                $name = substr($arg, 2);

                if (str_contains($name, '=')) {
                    [$key, $value] = explode('=', $name, 2);
                    $that->named[$key] = $value;
                    continue;
                }

                if (isset($argv[0]) && $argv[0][0] !== '-') {
                    $that->named[$name] = array_shift($argv);
                    continue;
                }

                $that->named[$name] = true;
                continue;
            }

            if ($arg[0] === '-') {
                $name = substr($arg, 1, 1);
                $value = substr($arg, 2);

                if (str_contains($value, '=')) {
                    break;
                }

                if (!empty($value)) {
                    $that->named[$name] = $value;
                    continue;
                }

                if (isset($argv[0]) && $argv[0][0] !== '-') {
                    $that->named[$name] = array_shift($argv);
                    continue;
                }

                $that->named[$name] = true;
                continue;
            }

            if ($that->command === '') {
                $that->command = $arg;
            } else {
                $that->positional[] = $arg;
            }
        }

        return $that;
    }

    /**
     * Retrieves a specific argument value.
     *
     * Depending on the type of key provided, this method returns
     * either a positional argument (if an integer is provided) or
     * a named argument (if a string is provided). If the argument
     * is not found, the default value is returned.
     *
     * @param int|string $key
     * @param mixed      $default
     * @return mixed
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        if (is_int($key)) {
            return $this->positional[$key] ?? $default;
        }

        return $this->named[$key] ?? $default;
    }
}
