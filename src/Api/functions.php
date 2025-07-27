<?php
declare(strict_types=1);

use Essentio\Api\Jwt;

/**
 * Encode or decode a JWT payload.
 *
 * @template T of array<string,mixed>|string
 * @param T $payload
 * @return (T is string ? array : string)
 */
function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}
