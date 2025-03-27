<?php

namespace Essentio\Core;

use Stringable;
use Throwable;

use function array_merge;
use function flush;
use function header;
use function headers_sent;
use function http_response_code;
use function is_array;

/**
 * Represents an HTTP response that encapsulates the status code, headers,
 * and body. Provides methods to modify the response immutably and send it.
 */
class Response
{
    /** @var int */
    public protected(set) int $status = 200;

    /** @var array<string, mixed> */
    public protected(set) array $headers = [];

    /** @var bool|float|int|string|Stringable|null */
    public protected(set) bool|float|int|string|Stringable|null $body = null;

    /**
     * Returns a new Response instance with the specified HTTP status code.
     *
     * @param int $status
     * @return static
     */
    public function withStatus(int $status): static
    {
        $that = clone $this;
        $that->status = $status;
        return $that;
    }

    /**
     * Returns a new Response instance with additional headers merged into the existing headers.
     *
     * @param array<string, mixed> $headers
     * @return static
     */
    public function addHeaders(array $headers): static
    {
        $that = clone $this;
        $that->headers = array_merge($that->headers, $headers);
        return $that;
    }

    /**
     * Returns a new Response instance with the headers replaced by the provided array.
     *
     * @param array<string, mixed> $headers
     * @return static
     */
    public function withHeaders(array $headers): static
    {
        $that = clone $this;
        $that->headers = $headers;
        return $that;
    }

    /**
     * Returns a new Response instance with the specified body.
     *
     * @param bool|float|int|string|Stringable|null $body
     * @return static
     */
    public function withBody(bool|float|int|string|Stringable|null $body): static
    {
        $that = clone $this;
        $that->body = $body;
        return $that;
    }

    /**
     * Sends the HTTP response to the client.
     *
     * @param bool $soft
     * @return bool
     */
    public function send(bool $detachResponse = false): bool
    {
        if (headers_sent()) {
            return false;
        }

        try {
            http_response_code($this->status);

            foreach ($this->headers as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $i => $v) {
                        header("$key: $v", $i === 0);
                    }
                } else {
                    header("$key: $value", true);
                }
            }

            echo (string) $this->body;

            if ($detachResponse) {
                session_write_close();
                if (function_exists('fastcgi_finish_request')) {
                    return fastcgi_finish_request();
                } else {
                    flush();
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
