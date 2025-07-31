<?php

declare(strict_types=1);

namespace Essentio\Http;

use Essentio\FrameworkException;
use Throwable;

/**
 * @api
 */
class HttpException extends FrameworkException
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
     * Create a new HTTP exception with status and optional message.
     */
    public static function create(int $status, ?string $message = null, ?Throwable $throwable = null): static
    {
        return new static($message ?? (static::HTTP_STATUS[$status] ?? "Unknown Error"), $status, $throwable);
    }
}
