<?php

namespace Essentio\Core;

use SessionHandler;

/**
 * @api
 */
final class Session
{
    protected const FLASH_OLD = "__flash_old__";

    protected const FLASH_NEW = "__flash_new__";

    protected const CSRF_KEY = "__csrf_key__";

    /**
     * Start the session and prepare flash data for the request.
     *
     * @param ?SessionHandler $handler
     * @return static
     */
    public static function create(?SessionHandler $handler = null): static
    {
        if ($handler !== null) {
            session_set_save_handler($handler);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[static::FLASH_OLD] = $_SESSION[static::FLASH_NEW] ?? [];
        $_SESSION[static::FLASH_NEW] = [];
        return new static();
    }

    /**
     * Set a session value.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set(string $key, mixed $value): mixed
    {
        return $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Set flash data for the next request.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function setFlash(string $key, mixed $value): mixed
    {
        return $_SESSION[static::FLASH_NEW][$key] = $value;
    }

    /**
     * Get flash data from the previous request.
     *
     * @param string $key
     * @return mixed
     */
    public function getFlash(string $key): mixed
    {
        return $_SESSION[static::FLASH_OLD][$key] ?? null;
    }

    /**
     * Get or generate a CSRF token.
     *
     * @return string
     */
    public function getCsrf(): string
    {
        return $_SESSION[static::CSRF_KEY] ??= bin2hex(random_bytes(32));
    }

    /**
     * Validate and rotate a CSRF token.
     *
     * @param string $csrf
     * @return bool
     */
    public function verifyCsrf(string $csrf): bool
    {
        if ($valid = hash_equals($_SESSION[static::CSRF_KEY] ?? "", $csrf)) {
            $_SESSION[static::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $valid;
    }
}
