<?php

namespace Essentio\Core;

use Stringable;

class Response
{
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public bool|float|int|string|Stringable|null $body = null
    ) {}

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function appendHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function setBody(bool|float|int|string|Stringable|null $body): static
    {
        $this->body = $body;
        return $this;
    }

    public function send(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $i => $v) {
                    header("{$key}: {$v}", $i === 0);
                }
            } else {
                header("{$key}: {$value}", true);
            }
        }

        echo (string) $this->body;
    }
}
