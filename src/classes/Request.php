<?php

namespace Zen\Core;

/**
 * Encapsulates an HTTP request by extracting data from PHP superglobals.
 * Provides methods to initialize request properties such as method, scheme,
 * host, port, path, parameters, headers, cookies, files, and body content.
 */
class Request
{
    /** @var string */
    public protected(set) string $method {
        set => \strtoupper($value);
    }

    /** @var string */
    public protected(set) string $scheme {
        set => \strtolower($value);
    }

    /** @var ?string */
    public protected(set) ?string $host;

    /** @var ?int */
    public protected(set) ?int $port;

    /** @var string */
    public protected(set) string $path;

    /** @var array<string, mixed> */
    public protected(set) array $parameters;

    /** @var array<string, mixed> */
    public protected(set) array $query;

    /** @var array<string, mixed> */
    public protected(set) array $headers;

    /** @var array<string, mixed> */
    public protected(set) array $cookies;

    /** @var array<string, mixed> */
    public protected(set) array $files;

    /** @var string */
    public protected(set) string $rawInput;

    /** @var array<string, mixed> */
    public protected(set) array $body;

    /**
     * Initializes and returns a new Request instance using PHP superglobals.
     *
     * This method sets the HTTP method, scheme, host, port, path, headers,
     * cookies, files, and body based on the current request environment.
     *
     * @return static
     */
    public static function init(): static
    {
        $that = new static;

        $that->method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (\filter_var($_SERVER['HTTPS'] ?? '', FILTER_VALIDATE_BOOLEAN)) {
            $that->scheme = 'https';
        } else {
            $that->scheme = 'http';
        }

        $host = null;
        $port = null;

        if (isset($_SERVER['HTTP_HOST'])) {
            if (\str_contains($_SERVER['HTTP_HOST'], ':')) {
                [$host, $port] = \explode(':', $_SERVER['HTTP_HOST'], 2);
                $port = (int) $port;
            } else {
                $host = $_SERVER['HTTP_HOST'];
                $port = $that->scheme === 'https' ? 443 : 80;
            }
        }

        $that->host = $host ?? $_SERVER['SERVER_NAME'];
        $that->port = $port ?? (int) $_SERVER['SERVER_PORT'];
        $that->path = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
        $that->parameters = [];
        $that->query = $_GET ?? [];
        $that->headers = \function_exists('getallheaders') ? (\getallheaders() ?: []) : [];
        $that->cookies = $_COOKIE ?? [];
        $that->files = $_FILES ?? [];

        $that->rawInput = \file_get_contents('php://input') ?: '';

        $contentType = $that->headers['Content-Type'] ?? '';
        $mimeType = \explode(';', $contentType, 2)[0];

        $that->body = match ($mimeType) {
            'application/x-www-form-urlencoded' => (function (string $input): array {
                \parse_str($input, $result);
                return $result;
            })($that->rawInput),
            'application/json' => \json_decode($that->rawInput, true),
            default => [],
        };

        return $that;
    }

    /**
     * Returns a new Request instance with the specified parameters.
     *
     * This method clones the current Request object and updates the
     * parameters property with the provided array.
     *
     * @param array $parameters
     * @return static
     */
    public function withParameters(array $parameters): static
    {
        $that = clone $this;
        $that->parameters = $parameters;
        return $that;
    }

    /**
     * Retrieve a value from the request parameters.
     *
     * Checks the custom parameters, then the parsed request body, and finally
     * the query parameters. If the key is not found, returns the provided default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key]
            ?? $this->body[$key]
            ?? $this->query[$key]
            ?? $default;
    }
}
