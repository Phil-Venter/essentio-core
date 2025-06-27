<?php

namespace Essentio\Core;

class Helper
{
    public function __construct(protected string $basePath) {}

    /**
     * Create a new Helper with the given base path.
     *
     * @param string $basePath
     * @return static
     */
    public static function create(string $basePath): static
    {
        return new static(rtrim($basePath, "/"));
    }

    /**
     * Resolve a relative path from the base path.
     *
     * @param string $path
     * @return string
     */
    public function fromBase(string $path): string
    {
        return $this->basePath . "/" . ltrim($path, "/");
    }

    /**
     * Convert a string to a native type if possible.
     *
     * @param mixed $value
     * @return mixed
     */
    public function autoCast(mixed $value): mixed
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
