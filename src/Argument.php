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
    public function __construct(public string $command = "", public array $arguments = []) {}

    public static function create(?array $argv = null): static
    {
        $argv ??= $_SERVER["argv"] ?? [];
        array_shift($argv);

        if (empty($argv)) {
            return new static();
        }

        $command = "";
        $arguments = [];

        while ($arg = array_shift($argv)) {
            if ($arg === "--") {
                $arguments = array_merge($arguments, $argv);
                break;
            }

            if (str_starts_with((string) $arg, "--")) {
                $option = substr((string) $arg, 2);

                if (str_contains($option, "=")) {
                    [$key, $value] = explode("=", $option, 2);
                } elseif (isset($argv[0]) && $argv[0][0] !== "-") {
                    $key = $option;
                    $value = array_shift($argv);
                } else {
                    $key = $option;
                    $value = true;
                }

                $arguments[$key] = $value;
                continue;
            }

            if ($arg[0] === "-") {
                $key = $arg[1];
                $value = substr((string) $arg, 2);

                if (empty($value)) {
                    if (isset($argv[0]) && $argv[0][0] !== "-") {
                        $value = array_shift($argv);
                    } else {
                        $value = true;
                    }
                }

                $arguments[$key] = $value;
                continue;
            }

            if (empty($command)) {
                $command = $arg;
            } else {
                $arguments[] = $arg;
            }
        }

        return new static($command, $arguments);
    }

    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }
}
