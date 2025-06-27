<?php

namespace Essentio\Core;

class Argument
{
    public function __construct(public readonly string $command = "", protected array $arguments = []) {}

    /**
     * Parse CLI arguments into command and options.
     *
     * @param Helper $helper
     * @param list<string>|null $argv
     * @return static
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

                if (empty($value)) {
                    if (isset($argv[0]) && $argv[0][0] !== "-") {
                        $value = array_shift($argv);
                    } else {
                        $value = true;
                    }
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
     *
     * @param int|string $key
     * @return mixed
     */
    public function get(int|string $key): mixed
    {
        return $this->arguments[$key] ?? null;
    }
}
