<?php
declare(strict_types=1);

use Essentio\Extra\HttpClient;
use Essentio\Extra\Query;

/**
 * Send an HTTP request via HttpClient and return a Response.
 *
 * @param array<string,mixed> $headers
 */
function curl(string $method, string $url, array $headers = [], string $body = ""): HttpClient
{
    return HttpClient::request($method, $url, $headers, $body);
}

/**
 * Get a Query builder instance.
 */
function query(): Query
{
    return app(Query::class);
}
