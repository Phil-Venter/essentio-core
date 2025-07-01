<?php

use Essentio\Core\FrameworkException;
use Essentio\Core\Jwt;

beforeEach(function () {
    $this->secret = "test-secret";
    $this->issuer = "test-issuer";
    $this->jwt = new Jwt($this->secret, $this->issuer);
});

it("encodes and decodes a simple payload", function () {
    $token = $this->jwt->encode(["user_id" => 1]);

    expect($token)->toBeString();

    $decoded = $this->jwt->decode($token);

    expect($decoded["user_id"])->toBe(1);
    expect($decoded["iss"])->toBe($this->issuer);
});

it("rejects token with invalid signature", function () {
    $token = $this->jwt->encode(["user_id" => 1]);

    [$header, $payload] = explode(".", $token);
    $tampered = implode(".", [$header, $payload, "invalidsignature"]);

    expect(fn() => $this->jwt->decode($tampered))
        ->toThrow(FrameworkException::class)
        ->and(fn($e) => expect($e->getMessage())->toBe("Invalid token signature"));
});

it("rejects token with wrong issuer", function () {
    $otherJwt = new Jwt($this->secret, "wrong-issuer");
    $token = $otherJwt->encode(["user_id" => 1]);

    expect(fn() => $this->jwt->decode($token))->toThrow(FrameworkException::class)->and(fn($e) => expect($e->getMessage())->toBe("Invalid issuer"));
});

it("rejects expired token", function () {
    $token = $this->jwt->encode(["exp" => time() - 10]);

    expect(fn() => $this->jwt->decode($token))
        ->toThrow(FrameworkException::class)
        ->and(fn($e) => expect($e->getMessage())->toBe("Token has expired"));
});

it("rejects token not yet valid (nbf)", function () {
    $token = $this->jwt->encode(["nbf" => time() + 10]);

    expect(fn() => $this->jwt->decode($token))
        ->toThrow(FrameworkException::class)
        ->and(fn($e) => expect($e->getMessage())->toBe("Token not valid yet"));
});

it("rejects token issued in the future (iat)", function () {
    $token = $this->jwt->encode(["iat" => time() + 10]);

    expect(fn() => $this->jwt->decode($token))
        ->toThrow(FrameworkException::class)
        ->and(fn($e) => expect($e->getMessage())->toBe("Token not valid yet"));
});

it("accepts token with valid exp, iat, and nbf", function () {
    $now = time();

    $token = $this->jwt->encode([
        "exp" => $now + 60,
        "iat" => $now - 60,
        "nbf" => $now - 30,
        "role" => "admin",
    ]);

    $payload = $this->jwt->decode($token);

    expect($payload["role"])->toBe("admin");
});
