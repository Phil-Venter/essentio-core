<?php

namespace Essentio\Core;

use function array_merge;
use function array_shift;
use function explode;
use function str_contains;
use function str_starts_with;
use function substr;

class Argument
{
    /** @var string */
    public protected(set) string $command = '';

    /** @var array<int|string, string|int|bool|null> */
    public protected(set) array $arguments = [];

    /**
     * Initializes and parses the command-line arguments.
     *
     * @param list<string>|null $argv
     * @return static
     */
    public static function init(?array $argv = null): static
    {
        $argv ??= $_SERVER['argv'] ?? [];
        $that = new static;
        array_shift($argv);

        if (empty($argv)) {
            return $that;
        }

        while ($arg = array_shift($argv)) {
            if ($arg === '--') {
                $that->arguments = array_merge($that->arguments, $argv);
                break;
            }

            if (str_starts_with((string) $arg, '--')) {
                $option = substr((string) $arg, 2);

                if (str_contains($option, '=')) {
                    [$key, $value] = explode('=', $option, 2);
                } elseif (isset($argv[0]) && $argv[0][0] !== '-') {
                    $key = $option;
                    $value = array_shift($argv);
                } else {
                    $key = $option;
                    $value = true;
                }

                $that->arguments[$key] = $value;
                continue;
            }

            if ($arg[0] === '-') {
                $key = $arg[1];
                $value = substr((string) $arg, 2);

                if (empty($value)) {
                    if (isset($argv[0]) && $argv[0][0] !== '-') {
                        $value = array_shift($argv);
                    } else {
                        $value = true;
                    }
                }

                $that->arguments[$key] = $value;
                continue;
            }

            if (empty($that->command)) {
                $that->command = $arg;
            } else {
                $that->arguments[] = $arg;
            }
        }

        return $that;
    }

    /**
     * Retrieves a specific argument value.
     *
     * @param int|string $key
     * @param mixed      $default
     * @return mixed
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }
}
