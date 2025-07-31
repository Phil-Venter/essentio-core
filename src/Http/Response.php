<?php

declare(strict_types=1);

namespace Essentio\Http;

use Stringable;

/**
 * @api
 */
class Response
{
    public int $status = 200;

    public array $headers = [];

    public bool|float|int|string|Stringable|null $body = null;

    /**
     * Set the HTTP status code.
     */
    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Add headers to the response.
     *
     * @param array<string,string|list<string>> $headers
     */
    public function addHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Replace all response headers.
     *
     * @param array<string,string|list<string>> $headers
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the response body.
     */
    public function setBody(bool|float|int|string|Stringable|null $body): static
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Send the HTTP response to the client.
     */
    public function send(bool $flush = false): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            foreach ((array) $value as $i => $v) {
                header(sprintf("%s: %s", $key, $v), $i === 0);
            }
        }

        header_remove("X-Powered-By");
        echo (string) $this->body;

        if (!$flush) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        $flags = PHP_OUTPUT_HANDLER_REMOVABLE | PHP_OUTPUT_HANDLER_FLUSHABLE;

        foreach (ob_get_status(true) as $stat) {
            if (($stat['del'] ?? false) || (($stat['flags'] ?? 0) & $flags) === $flags) {
                @ob_end_flush();
            }
        }

        flush();
    }
}
