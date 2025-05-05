<?php

namespace Essentio\Core;

use function session_start;
use function session_status;

class Session
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION["_flash"]["old"] = $_SESSION["_flash"]["new"] ?? [];
        $_SESSION["_flash"]["new"] = [];
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
        $_SESSION["_flash"]["new"][$key] = $value;
    }

    /**
     * Retrieves a value from the flash (old) session or regular session by key.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $_SESSION["_flash"]["old"][$key] ?? ($_SESSION[$key] ?? null);
    }
}
