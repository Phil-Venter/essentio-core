<?php

namespace Essentio\Core;

use function explode;
use function file_get_contents;
use function filter_var;
use function function_exists;
use function getallheaders;
use function json_decode;
use function parse_str;
use function parse_url;
use function str_contains;
use function strtolower;
use function strtoupper;
use function trim;

/**
 * Encapsulates an HTTP request by extracting data from PHP superglobals.
 * Provides methods to initialize request properties such as method, scheme,
 * host, port, path, parameters, headers, cookies, files, and body content.
 */
class Request
{
    /** @var string */
    public protected(set) string $method {
        set => strtoupper($value);
    }

    /** @var string */
    public protected(set) string $scheme {
        set => strtolower($value);
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

        if (filter_var($_SERVER['HTTPS'] ?? '', FILTER_VALIDATE_BOOLEAN)) {
            $that->scheme = 'https';
        } else {
            $that->scheme = 'http';
        }

        $host = null;
        $port = null;

        if (isset($_SERVER['HTTP_HOST'])) {
            if (str_contains($_SERVER['HTTP_HOST'], ':')) {
                [$host, $port] = explode(':', $_SERVER['HTTP_HOST'], 2);
                $port = (int) $port;
            } else {
                $host = $_SERVER['HTTP_HOST'];
                $port = $that->scheme === 'https' ? 443 : 80;
            }
        }

        $that->host = $host ?? $_SERVER['SERVER_NAME'];
        $that->port = $port ?? (int) $_SERVER['SERVER_PORT'];
        $that->path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
        $that->parameters = [];
        $that->query = $_GET ?? [];
        $that->headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $that->cookies = $_COOKIE ?? [];
        $that->files = $_FILES ?? [];

        $that->rawInput = file_get_contents('php://input') ?: '';

        $contentType = $that->headers['Content-Type'] ?? '';
        $mimeType = explode(';', $contentType, 2)[0];

        $that->body = match ($mimeType) {
            'application/x-www-form-urlencoded' => (function (string $input): array {
                parse_str($input, $result);
                return $result;
            })($that->rawInput),
            'application/json' => json_decode($that->rawInput, true),
            default => [],
        };

        return $that;
    }

    /**
     * Sets custom parameters for the request.
     *
     * This method allows you to override the request parameters with a custom
     * associative array. These parameters can later be used by the get() method
     * to retrieve specific request values.
     *
     * @param array<string, mixed> $parameters
     * @return static
     */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Retrieve a value from the request parameters.
     *
     * Checks the custom parameters, then the query parameters.
     * If the key is not found, returns the provided default.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key]
            ?? $this->query[$key]
            ?? $default;
    }

    /**
     * Extracts a specific parameter from the incoming request data.
     *
     * This function dynamically selects the data source based on the HTTP method used. For methods typically devoid
     * of a payload (e.g., GET, HEAD, OPTIONS, TRACE), it pulls the value from the query string. For methods expected
     * to contain a body (such as POST, PUT, or PATCH), it retrieves the value from the request payload.
     * If the parameter is absent in the relevant dataset, the function returns the specified fallback.
     *
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        if (in_array($this->method, ['GET', 'HEAD', 'OPTIONS', 'TRACE'])) {
            return $this->query[$key] ?? $default;
        }
        return $this->body[$key] ?? $default;
    }
}
