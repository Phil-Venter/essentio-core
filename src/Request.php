<?php

namespace Essentio\Core;

use Throwable;

use function count;
use function explode;
use function file_get_contents;
use function function_exists;
use function getallheaders;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function parse_url;
use function simplexml_load_string;
use function str_contains;
use function strtoupper;
use function trim;

class Request
{
    public array $errors = [];

    public function __construct(
        public readonly string $method,
        public readonly int $port,
        public readonly string $path,
        protected array $query,
        public readonly string $contentType,
        public readonly array $headers,
        public readonly array $cookies,
        public readonly array $files,
        protected array $body,
        public array $parameters
    ) {}

    public static function create(
        ?array $server = null,
        ?array $headers = null,
        ?array $query = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
        ?string $body = null
    ): static {
        $server ??= $_SERVER ?? [];
        $post ??= $_POST ?? [];
        $query ??= $_GET ?? [];
        $cookies ??= $_COOKIE ?? [];
        $files ??= $_FILES ?? [];
        $headers ??= function_exists("getallheaders") ? getallheaders() : [];
        $rawInput = $body ?? file_get_contents("php://input");

        $method = strtoupper($post["_method"] ?? ($server["REQUEST_METHOD"] ?? "GET"));
        $path = trim(parse_url($server["REQUEST_URI"] ?? "", PHP_URL_PATH) ?? "", "/");

        $hostHeader = $server["HTTP_HOST"] ?? null;
        if ($hostHeader && str_contains((string) $hostHeader, ":")) {
            [, $port] = explode(":", (string) $hostHeader, 2);
            $port = (int) $port;
        } else {
            $port = (int) ($server["SERVER_PORT"] ?? (empty($server["HTTPS"]) ? 80 : 443));
        }

        $contentType = explode(";", $headers["Content-Type"] ?? "", 2)[0];

        $flatFiles = [];
        foreach ($files as $info) {
            if (!is_array($info["name"] ?? null)) {
                if (($info["error"] ?? null) === UPLOAD_ERR_OK) {
                    $flatFiles[] = $info;
                }

                continue;
            }

            for ($i = 0; $i < count($info["name"]); $i++) {
                if ($info["error"][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $temp = [];
                foreach ($info as $key => $vals) {
                    $temp[$key] = $vals[$i];
                }

                $flatFiles[] = $temp;
            }
        }

        $parsedBody = match ($contentType) {
            "application/json" => json_decode($rawInput, true) ?? [],
            "application/xml", "text/xml" => ($xml = simplexml_load_string($rawInput)) ? json_decode(json_encode($xml), true) : [],
            default => $post,
        };

        return new static($method, $port, $path, $query, $contentType, $headers, $cookies, $flatFiles, $parsedBody, []);
    }

    public function get(string $field): mixed
    {
        return $this->parameters[$field] ?? ($this->query[$field] ?? null);
    }

    public function input(string $field): mixed
    {
        return in_array($this->method, ["GET", "HEAD", "OPTIONS", "TRACE"], true)
            ? $this->get($field)
            : $this->body[$field] ?? ($this->parameters[$field] ?? null);
    }

    public function sanitize(array $rules): array|false
    {
        $this->errors = [];
        $sanitized = [];

        foreach ($rules as $field => $chain) {
            $value = $this->input($field);

            try {
                foreach ((array) $chain as $fn) {
                    $value = $fn($value);
                }

                $sanitized[$field] = $value;
            } catch (Throwable $e) {
                $this->errors[$field][] = $e->getMessage();
            }
        }

        return empty($this->errors) ? $sanitized : false;
    }
}
