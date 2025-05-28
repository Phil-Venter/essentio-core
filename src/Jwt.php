<?php

namespace Essentio\Core;

use Exception;

class Jwt
{
    /**
     * @param string $secret Secret key for HMAC signing and verification.
     */
    public function __construct(protected string $secret) {}

    /**
     * Encodes a payload array into a JWT.
     *
     * @param array $payload Claims to be embedded in the token.
     * @return string Encoded JWT.
     */
    public function encode(array $payload): string
    {
        $header = ["alg" => "HS256", "typ" => "JWT"];
        $segments = [$this->base64url_encode(json_encode($header)), $this->base64url_encode(json_encode($payload))];
        $signingInput = implode(".", $segments);
        $signature = $this->sign($signingInput);

        $segments[] = $this->base64url_encode($signature);
        return implode(".", $segments);
    }

    /**
     * Decodes and verifies a JWT.
     *
     * @param string $token JWT to be decoded.
     * @return array Decoded payload.
     * @throws Exception If the signature is invalid or token is expired.
     */
    public function decode(string $token): array
    {
        [$header64, $payload64, $signature64] = explode(".", $token);
        $signingInput = "$header64.$payload64";
        $signature = $this->base64url_decode($signature64);

        if (!hash_equals($this->sign($signingInput), $signature)) {
            throw new Exception("Invalid token signature");
        }

        $payload = json_decode($this->base64url_decode($payload64), true);

        if (isset($payload["exp"]) && time() > $payload["exp"]) {
            throw new Exception("Token has expired");
        }

        return $payload;
    }

    /**
     * Encodes data using base64 URL-safe encoding.
     *
     * @param string $data Input data.
     * @return string URL-safe base64 encoded string.
     */
    protected function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * Decodes base64 URL-safe encoded data.
     *
     * @param string $data Encoded string.
     * @return string Decoded data.
     */
    protected function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, "-_", "+/"));
    }

    /**
     * Generates an HMAC-SHA256 signature.
     *
     * @param string $input The data to sign.
     * @return string Binary HMAC signature.
     */
    protected function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}
