<?php

namespace Essentio\Core;

class Environment
{
    public function __construct(public array $data = []) {}

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

            [$key, $value] = explode("=", $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (preg_match('/^(["\']).*\1$/', $value)) {
                $value = substr($value, 1, -1);
            } else {
                $lower = strtolower($value);
                $value = match (true) {
                    $lower === "true" => true,
                    $lower === "false" => false,
                    $lower === "null" => null,
                    is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
                    default => $value,
                };
            }

            $this->data[$key] = $value;
        }

        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
