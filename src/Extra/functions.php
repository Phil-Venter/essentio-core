<?php

use Essentio\Core\Extra\HttpClient;
use Essentio\Core\Extra\Query;
use Essentio\Core\Response;

/**
 * Send an HTTP request via HttpClient and return a Response.
 *
 * @param string $method  HTTP verb (e.g. 'GET', 'POST')
 * @param string $url     Full URL to request
 * @param array $headers  Associative array of headers (e.g. ['Accept' => 'application/json'])
 * @param string $body    Request body as a raw string
 * @return Response       Structured response with status, headers, and body
 */
function curl(string $method, string $url, array $headers = [], string $body = ""): Response
{
    return HttpClient::request($method, $url, $headers, $body);
}

/**
 * Get a Query builder instance.
 *
 * @return Query
 */
function query(): Query
{
    return app(Query::class);
}
