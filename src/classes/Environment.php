<?php

namespace Zen\Core;

/**
 * Handles the loading and retrieval of environment variables from a file.
 * The file is parsed line-by-line, skipping comments and empty lines, while
 * converting the configuration into an associative array.
 */
class Environment
{
    /** @var array<string, mixed> */
    public protected(set) array $data = [];

    /**
     * Loads environment variables from a file.
     *
     * Reads a file line-by-line, ignoring lines that are empty or start with a '#'.
     * Each valid line is parsed into a key-value pair. Values are trimmed, unquoted if necessary,
     * and typecasted to boolean, null, or numeric values when applicable.
     *
     * @param string $file
     * @return static
     */
    public function load(string $file): static
    {
        if (!\file_exists($file)) {
            return $this;
        }

        foreach (\file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (\trim($line)[0] === '#') {
                continue;
            }

            $parts = \explode('=', $line, 2);

            if (\count($parts) !== 2) {
                continue;
            }

            $name = \trim($parts[0]);
            $value = \trim($parts[1]);

            if (isset($value[0]) && (($value[0] === '"' && \substr($value, -1) === '"') || ($value[0] === "'" && \substr($value, -1) === "'"))) {
                $value = \substr($value, 1, -1);
            } else {
                $lower = \strtolower($value);
                $value = match (true) {
                    $lower === 'true'  => true,
                    $lower === 'false' => false,
                    $lower === 'null'  => null,
                    \is_numeric($value) => (\str_contains($value, 'e') || \str_contains($value, '.'))
                        ? (float)$value
                        : (int)$value,
                    default            => $value,
                };
            }

            $this->data[$name] = $value;
        }

        return $this;
    }

    /**
     * Retrieves an environment variable.
     *
     * Returns the value of the specified environment variable from the loaded data.
     * If the variable is not found, the method returns the provided default value.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
