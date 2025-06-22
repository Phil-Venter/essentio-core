<?php

namespace Essentio\Core;

use RuntimeException;

use function base64_decode;
use function base64_encode;
use function explode;
use function hash_equals;
use function hash_hmac;
use function implode;
use function json_decode;
use function json_encode;
use function rtrim;
use function strtr;
use function time;

class Jwt
{
    public function __construct(protected string $secret, protected string $issuer) {}

    public function encode(array $payload): string
    {
        $header = ["alg" => "HS256", "typ" => "JWT"];
        $payload["iss"] = $this->issuer;
        $segments = [$this->base64url_encode(json_encode($header)), $this->base64url_encode(json_encode($payload))];
        $signingInput = implode(".", $segments);
        $signature = $this->sign($signingInput);

        $segments[] = $this->base64url_encode($signature);
        return implode(".", $segments);
    }

    public function decode(string $token): array
    {
        [$header64, $payload64, $signature64] = explode(".", $token);
        $signingInput = "$header64.$payload64";
        $signature = $this->base64url_decode($signature64);

        if (!hash_equals($this->sign($signingInput), $signature)) {
            throw new RuntimeException("Invalid token signature");
        }

        $payload = json_decode($this->base64url_decode($payload64), true);

        if (isset($payload["iss"]) && $this->issuer !== $payload["iss"]) {
            throw new RuntimeException("Invalid issuer");
        }

        if (isset($payload["exp"]) && time() > $payload["exp"]) {
            throw new RuntimeException("Token has expired");
        }

        if (isset($payload["iat"]) && time() < $payload["iat"]) {
            throw new RuntimeException("Token not valid yet");
        }

        if (isset($payload["nbf"]) && time() < $payload["nbf"]) {
            throw new RuntimeException("Token not valid yet");
        }

        return $payload;
    }

    protected function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    protected function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, "-_", "+/"));
    }

    protected function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}
