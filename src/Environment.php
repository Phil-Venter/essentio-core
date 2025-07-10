<?php

namespace Essentio\Core;

/**
 * @api
 */
class Environment
{
    public function __construct(protected array $data = []) {}

    /**
     * Load and parse environment variables from a .env file.
     */
    public static function create(Helper $helper, ?string $file = null): static
    {
        if (!file_exists($file = $helper->fromBase($file ?? ".env"))) {
            return new static();
        }

        $data = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if ($line === "") {
                continue;
            }

            if ($line === "0") {
                continue;
            }

            if ($line[0] === "#") {
                continue;
            }

            if (!str_contains($line, "=")) {
                continue;
            }

            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            [$key, $value] = explode("=", $line, 2);
            $data[trim($key)] = $helper->autoCast(trim($value));
        }

        return new static($data);
    }

    /**
     * Get a value from the environment data.
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
