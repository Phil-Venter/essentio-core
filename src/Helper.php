<?php

namespace Essentio\Core;

/**
 * @api
 */
final readonly class Helper
{
    public function __construct(private string $basePath) {}

    /**
     * Create a new Helper with the given base path.
     */
    public static function create(string $basePath): static
    {
        return new self(rtrim($basePath, "/"));
    }

    /**
     * Resolve a relative path from the base path.
     */
    public function fromBase(string $path): string
    {
        return $this->basePath . "/" . ltrim($path, "/");
    }

    /**
     * Convert a string to a native type if possible.
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
