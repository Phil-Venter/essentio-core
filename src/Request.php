<?php

namespace Essentio\Core;

use function array_map;
use function explode;
use function file_get_contents;
use function filter_var;
use function function_exists;
use function getallheaders;
use function in_array;
use function json_decode;
use function json_encode;
use function libxml_use_internal_errors;
use function parse_str;
use function parse_url;
use function simplexml_load_string;
use function str_contains;
use function strtolower;
use function strtoupper;
use function trim;

class Request
{
    /** @var string */
    public protected(set) string $method { set => strtoupper($value); }

    /** @var string */
    public protected(set) string $scheme { set => strtolower($value); }

    /** @var ?string */
    public protected(set) ?string $host;

    /** @var ?int */
    public protected(set) ?int $port;

    /** @var string */
    public protected(set) string $path;

    /** @var array<string,mixed> */
    public protected(set) array $parameters;

    /** @var array<string,mixed> */
    public protected(set) array $query;

    /** @var array<string,mixed> */
    public protected(set) array $headers;

    /** @var array<string,mixed> */
    public protected(set) array $cookies;

    /** @var array<string,mixed> */
    public protected(set) array $files;

    /** @var string */
    public protected(set) string $rawInput;

    /** @var array<string,mixed> */
    public protected(set) array $body;

    /**
     * Initializes and returns a new Request instance using PHP superglobals.
     *
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $headers
     * @param array<string, mixed>|null $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookie
     * @param array<string, mixed>|null $files
     * @param string|null               $body
     * @return static
     */
    public static function init(
        ?array $server = null,
        ?array $headers = null,
        ?array $get = null,
        ?array $post = null,
        ?array $cookie = null,
        ?array $files = null,
        ?string $body = null
    ): static {
        $server ??= $_SERVER ?? [];
        $post ??= $_POST ?? [];

        $that = new static();

        $that->method = $post["_method"] ?? $server["REQUEST_METHOD"] ?? "GET";
        $that->scheme = filter_var($server["HTTPS"] ?? "", FILTER_VALIDATE_BOOLEAN) ? "https" : "http";

        $host = null;
        $port = null;

        if (isset($server["HTTP_HOST"])) {
            if (str_contains($server["HTTP_HOST"], ":")) {
                [$host, $port] = explode(":", $server["HTTP_HOST"], 2);
                $port = (int) $port;
            } else {
                $host = $server["HTTP_HOST"];
                $port = $that->scheme === "https" ? 443 : 80;
            }
        }

        $that->host = $host ?? $server["SERVER_NAME"] ?? "localhost";
        $that->port = (int) ($port ?? $server["SERVER_PORT"] ??  80);

        $that->path = trim(parse_url($server["REQUEST_URI"] ?? "", PHP_URL_PATH) ?? "", "/");
        $that->parameters = [];
        $that->query = $get ?? $_GET ?? [];
        $that->headers = $headers ?? (function_exists("getallheaders") ? (getallheaders() ?: []) : []);
        $that->cookies = $cookie ?? $_COOKIE ?? [];
        $that->files = $files ?? $_FILES ?? [];

        $that->rawInput = $body ?? file_get_contents("php://input") ?: "";

        $contentType = $that->headers["Content-Type"] ?? "";
        $mimeType = explode(";", $contentType, 2)[0];

        $that->body = match ($mimeType) {
            "application/x-www-form-urlencoded" => (function (string $input): array {
                parse_str($input, $result);
                return $result;
            })($that->rawInput),
            "application/json" => json_decode($that->rawInput, true),
            "application/xml", "text/xml" => (function (string $input): array {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($input);
                return $xml ? json_decode(json_encode($xml), true) : [];
            })($that->rawInput),
            default => $post,
        };

        return $that;
    }

    /**
     * Sets custom parameters for the request.
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
     * @param array|string $key
     * @param mixed        $default
     * @return mixed
     */
    public function get(array|string $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            return array_map(fn($k) => $this->get($k, $default), $key);
        }

        return $this->parameters[$key]
            ?? $this->query[$key]
            ?? $default;
    }

    /**
     * Extracts a specific parameter from the incoming request data.
     *
     * @param array|string $key
     * @param mixed        $default
     * @return mixed
     */
    public function input(array|string $key, mixed $default = null): mixed
    {
        if (is_array($key)) {
            return array_map(fn($k) => $this->input($k, $default), $key);
        }

        if (in_array($this->method, ["GET", "HEAD", "OPTIONS", "TRACE"])) {
            return $this->query[$key] ?? $default;
        }

        return $this->body[$key] ?? $default;
    }
}
