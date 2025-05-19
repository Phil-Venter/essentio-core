<?php

namespace Essentio\Core;

use function session_start;
use function session_status;

class Session
{
    protected const FLASH_OLD = "\0FO";

    protected const FLASH_NEW = "\0FN";

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[static::FLASH_OLD] = $_SESSION[static::FLASH_NEW] ?? [];
        $_SESSION[static::FLASH_NEW] = [];
    }

    /**
     * Stores a value in the session under the specified key.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Stores a temporary flash value in the session under the specified key.
     *
     * @param string $key
     * @param mixed $value
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION[static::FLASH_NEW][$key] = $value;
    }

    /**
     * Retrieves a value from the flash (old) session or regular session by key.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $_SESSION[static::FLASH_OLD][$key] ?? ($_SESSION[$key] ?? null);
    }
}
