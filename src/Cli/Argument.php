<?php
declare(strict_types=1);

namespace Essentio\Cli;

/**
 * @api
 */
class Argument
{
    /**
     * @param array<int|string,mixed> $arguments
     */
    public function __construct(public readonly string $command = "", protected array $arguments = []) {}

    /**
     * Parse CLI arguments into command and options.
     *
     * @param list<string>|null $argv
     */
    public static function create(?array $argv = null): static
    {
        $argv ??= $_SERVER["argv"] ?? [];

        if (count($argv) <= 1) {
            return new static();
        }

        array_shift($argv);
        $command = "";
        $arguments = [];

        while (($arg = array_shift($argv)) !== null) {
            if ($arg === "--") {
                $arguments = array_merge($arguments, array_map(static::autoCast(...), $argv));
                break;
            }

            if (str_starts_with((string) $arg, "--")) {
                $option = substr((string) $arg, 2);

                if (mb_stripos($option, "=") !== false) {
                    /** @psalm-suppress PossiblyUndefinedArrayOffset */
                    [$key, $value] = explode("=", $option, 2);
                } elseif (isset($argv[0]) && $argv[0][0] !== "-") {
                    $key = $option;
                    $value = array_shift($argv);
                } else {
                    $key = $option;
                    $value = true;
                }

                $arguments[$key] = static::autoCast($value);
                continue;
            }

            if ($arg[0] === "-") {
                $key = $arg[1];
                $value = substr((string) $arg, 2);

                if ($value === "") {
                    $value = isset($argv[0]) && $argv[0][0] !== "-" ? array_shift($argv) : true;
                }

                $arguments[$key] = static::autoCast($value);
                continue;
            }

            if (empty($command)) {
                $command = $arg;
            } else {
                $arguments[] = static::autoCast($arg);
            }
        }

        return new static($command, $arguments);
    }

    /**
     * Get an argument by key or index.
     */
    public function get(int|string $key): mixed
    {
        return $this->arguments[$key] ?? null;
    }

    /**
     * Convert a string to a native type if possible.
     */
    protected static function autoCast(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('/^(["\']).*\1$/', $value)) {
            return substr($value, 1, -1);
        }

        $lower = strtolower($value);

        return match (true) {
            $lower === "true" => true,
            $lower === "false" => false,
            $lower === "null" => null,
            is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
            default => $value,
        };
    }
}
