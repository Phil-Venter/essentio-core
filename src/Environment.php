<?php

namespace Essentio\Core;

use function explode;
use function file;
use function file_exists;
use function str_contains;
use function trim;

class Environment
{
    public function __construct(protected array $data = []) {}

    public static function create(Helper $helper, ?string $file = null): static
    {
        if (!file_exists($file = $helper->fromBase($file ?? ".env"))) {
            return new static();
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $data = [];

        foreach ($lines as $line) {
            if (empty($line) || $line[0] === "#" || !str_contains($line, "=")) {
                continue;
            }

            [$key, $value] = explode("=", $line, 2);
            $data[trim($key)] = $helper->autoCast(trim($value));
        }

        return new static($data);
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}
