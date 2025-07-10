<?php

namespace Essentio\Core;

/**
 * @api
 */
class Argument
{
    public function __construct(public readonly string $command = "", private array $arguments = []) {}

    /**
     * Parse CLI arguments into command and options.
     *
     * @param list<string>|null $argv
     */
    public static function create(Helper $helper, ?array $argv = null): static
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
                $arguments = array_merge($arguments, array_map($helper->autoCast(...), $argv));
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

                $arguments[$key] = $helper->autoCast($value);
                continue;
            }

            if ($arg[0] === "-") {
                $key = $arg[1];
                $value = substr((string) $arg, 2);

                if ($value === "" || $value === "0") {
                    $value = isset($argv[0]) && $argv[0][0] !== "-" ? array_shift($argv) : true;
                }

                $arguments[$key] = $helper->autoCast($value);
                continue;
            }

            if (empty($command)) {
                $command = $arg;
            } else {
                $arguments[] = $helper->autoCast($arg);
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
}
