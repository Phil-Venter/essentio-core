<?php

namespace Essentio\Core;

use RuntimeException;

/**
 * @api
 */
final class Jwt
{
    public function __construct(protected string $secret, protected ?string $issuer = null) {}

    /**
     * Create a new Jwt instance from environment values.
     *
     * @param Environment $env
     * @return static
     */
    public static function create(Environment $env): static
    {
        return new static($env->get("JWT_SECRET") ?? "", $env->get("JWT_ISSUER"));
    }

    /**
     * Encode a payload into a JWT string.
     *
     * @param array $payload
     * @return string
     */
    public function encode(array $payload): string
    {
        if ($this->issuer !== null) {
            $payload["iss"] = $this->issuer;
        }

        $segments = [$this->encodeBase64(json_encode(["alg" => "HS256", "typ" => "JWT"], JSON_THROW_ON_ERROR))];
        $segments[] = $this->encodeBase64(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->sign(implode(".", $segments));
        $segments[] = $this->encodeBase64($signature);

        return implode(".", $segments);
    }

    /**
     * Decode and validate a JWT string.
     *
     * @param string $token
     * @return array
     */
    public function decode(string $token): array
    {
        [$header64, $payload64, $signature64] = explode(".", $token);
        $signature = $this->decodeBase64($signature64);

        $header = json_decode($this->decodeBase64($header64), true);

        if (!is_array($header) || ($header["alg"] ?? null) !== "HS256") {
            throw new RuntimeException("Unsupported or missing algorithm");
        }

        if (!hash_equals($this->sign("$header64.$payload64"), $signature)) {
            throw new RuntimeException("Invalid token signature");
        }

        $payload = json_decode($this->decodeBase64($payload64), true);

        if (!is_array($payload)) {
            throw new RuntimeException("Invalid payload format");
        }

        if (($this->issuer ?? null) !== ($payload["iss"] ?? null)) {
            throw new RuntimeException("Invalid issuer");
        }

        if (isset($payload["exp"]) && time() > (int) $payload["exp"]) {
            throw new RuntimeException("Token has expired");
        }

        if (isset($payload["iat"]) && time() < (int) $payload["iat"]) {
            throw new RuntimeException("Token not valid yet");
        }

        if (isset($payload["nbf"]) && time() < (int) $payload["nbf"]) {
            throw new RuntimeException("Token not valid yet");
        }

        return $payload;
    }

    /**
     * Encode data to base64url format.
     *
     * @param string $data
     * @return string
     */
    protected function encodeBase64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * Decode data from base64url format.
     *
     * @param string $data
     * @return string
     */
    protected function decodeBase64(string $data): string
    {
        if ($remainder = strlen($data) % 4) {
            $data .= str_repeat("=", 4 - $remainder);
        }

        return base64_decode(strtr($data, "-_", "+/"));
    }

    /**
     * Sign input string using HMAC-SHA256.
     *
     * @param string $input
     * @return string
     */
    protected function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}
