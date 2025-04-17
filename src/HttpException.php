<?php

namespace Essentio\Core;

use Exception;
use Throwable;

class HttpException extends Exception
{
    public const HTTP_STATUS = [
        // Success
        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        204 => "No Content",

        // Redirection
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        307 => "Temporary Redirect",
        308 => "Permanent Redirect",

        // Client Errors
        400 => "Bad Request",
        401 => "Unauthorized",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",

        // Server Errors
        500 => "Internal Server Error",
    ];

    /**
     * Factory method to create a new HttpException instance.
     *
     * @param int $status HTTP status code (e.g., 404, 500).
     * @param string|null $message Optional custom error message.
     * @param Throwable|null $previous Optional previous exception for chaining.
     * @return static A new instance of the HttpException class.
     */
    public static function make(int $status, ?string $message = null, ?Throwable $previous = null): static
    {
        return new static($message ?? (static::HTTP_STATUS[$status] ?? "Unknown Error"), $status, $previous);
    }
}
