<?php

namespace Essentio\Core;

use function explode;
use function file;
use function file_exists;
use function is_numeric;
use function preg_match;
use function str_contains;
use function strtolower;
use function substr;
use function trim;

class Environment
{
    /** @var array<string, mixed> */
    public protected(set) array $data = [];

    /**
     * Loads key-value pairs from a .env file into memory.
     * Supports quoted values and auto type inference.
     *
     * @param string $file Path to .env file.
     * @return static
     */
     public function load(string $file): static
    {
        if (!file_exists($file)) {
            return $this;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            if (trim($line)[0] === "#" || !str_contains($line, "=")) {
                continue;
            }

            [$name, $value] = explode("=", $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (preg_match('/^(["\']).*\1$/', $value)) {
                $value = substr($value, 1, -1);
            } else {
                $lower = strtolower($value);
                $value = match (true) {
                    $lower === "true"  => true,
                    $lower === "false" => false,
                    $lower === "null"  => null,
                    is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
                    default            => $value,
                };
            }

            $this->data[$name] = $value;
        }

        return $this;
    }

    /**
     * Retrieves an environment value by key.
     *
     * @param string $key     Name of the variable.
     * @param mixed  $default Default value if not found.
     * @return mixed          The stored or default value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
