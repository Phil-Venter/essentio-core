<?php

declare(strict_types=1);

namespace Essentio\Api;

use Essentio\Environment;
use Essentio\FrameworkException;

/**
 * @api
 */
final readonly class Jwt
{
    public function __construct(private string $secret, private ?string $issuer = null) {}

    /**
     * Create a new Jwt instance from environment values.
     */
    public static function create(Environment $environment): static
    {
        return new self($environment->get("JWT_SECRET") ?? "", $environment->get("JWT_ISSUER"));
    }

    /**
     * Encode a payload into a JWT string.
     *
     * @param array<string,mixed> $payload
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
     * @return array<string,mixed>
     */
    public function decode(string $token): array
    {
        $parts = explode(".", $token);
        if (count($parts) !== 3) {
            throw new FrameworkException("Invalid token format");
        }

        [$header64, $payload64, $signature64] = $parts;
        $signature = $this->decodeBase64($signature64);

        $header = json_decode($this->decodeBase64($header64), true);

        if (!is_array($header) || ($header["alg"] ?? null) !== "HS256") {
            throw new FrameworkException("Unsupported or missing algorithm");
        }

        if (!hash_equals($this->sign(sprintf("%s.%s", $header64, $payload64)), $signature)) {
            throw new FrameworkException("Invalid token signature");
        }

        $payload = json_decode($this->decodeBase64($payload64), true);

        if (!is_array($payload)) {
            throw new FrameworkException("Invalid payload format");
        }

        if (($this->issuer ?? null) !== ($payload["iss"] ?? null)) {
            throw new FrameworkException("Invalid issuer");
        }

        if (isset($payload["exp"]) && time() > (int) $payload["exp"]) {
            throw new FrameworkException("Token has expired");
        }

        if (isset($payload["iat"]) && time() < (int) $payload["iat"]) {
            throw new FrameworkException("Token not valid yet");
        }

        if (isset($payload["nbf"]) && time() < (int) $payload["nbf"]) {
            throw new FrameworkException("Token not valid yet");
        }

        return $payload;
    }

    /**
     * Encode data to base64url format.
     */
    private function encodeBase64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * Decode data from base64url format.
     */
    private function decodeBase64(string $data): string
    {
        if (($remainder = strlen($data) % 4) !== 0) {
            $data .= str_repeat("=", 4 - $remainder);
        }

        $out = base64_decode(strtr($data, "-_", "+/"), true);

        if ($out === false) {
            throw new FrameworkException("Invalid base64url segment");
        }

        return $out;
    }

    /**
     * Sign input string using HMAC-SHA256.
     */
    private function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}
