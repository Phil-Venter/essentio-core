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
     */
    public static function create(?SessionHandler $sessionHandler = null): static
    {
        if ($sessionHandler instanceof SessionHandler) {
            session_set_save_handler($sessionHandler);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                "lifetime" => 0,
                "path" => "/",
                "domain" => "",
                "secure" => !empty($_SERVER["HTTPS"]),
                "httponly" => true,
                "samesite" => "Lax",
            ]);

            session_start();
        }

        $_SESSION[self::FLASH_OLD] = $_SESSION[self::FLASH_NEW] ?? [];
        $_SESSION[self::FLASH_NEW] = [];
        return new self();
    }

    /**
     * Set a session value.
     */
    public function set(string $key, mixed $value): mixed
    {
        return $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     */
    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Set flash data for the next request.
     */
    public function setFlash(string $key, mixed $value): mixed
    {
        return $_SESSION[self::FLASH_NEW][$key] = $value;
    }

    /**
     * Get flash data from the previous request.
     */
    public function getFlash(string $key): mixed
    {
        return $_SESSION[self::FLASH_OLD][$key] ?? null;
    }

    /**
     * Get or generate a CSRF token.
     */
    public function getCsrf(): string
    {
        return $_SESSION[self::CSRF_KEY] ??= bin2hex(random_bytes(32));
    }

    /**
     * Validate and rotate a CSRF token.
     */
    public function verifyCsrf(string $csrf): bool
    {
        if ($valid = hash_equals($_SESSION[self::CSRF_KEY] ?? "", $csrf)) {
            $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $valid;
    }
}
