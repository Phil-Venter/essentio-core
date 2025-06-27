<?php

namespace Essentio\Core;

use function is_numeric;
use function is_string;
use function ltrim;
use function preg_match;
use function rtrim;
use function strtolower;
use function substr;

class Helper
{
    public function __construct(protected string $basePath) {}

    public static function create(string $basePath): static
    {
        return new static(rtrim($basePath, "/"));
    }

    public function fromBase(string $path): string
    {
        return $this->basePath . "/" . ltrim($path, "/");
    }

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
