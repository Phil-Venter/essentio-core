<?php

use Essentio\Core\Response;

describe(Response::class, function () {
    it("withStatus returns a new instance with updated status", function () {
        $response = new Response();
        $newResponse = $response->withStatus(404);

        expect($newResponse->status)->toBe(404);
        // Original response remains unchanged.
        expect($response->status)->toBe(200);
    });

    it("withBody returns a new instance with updated body", function () {
        $response = new Response();
        $newResponse = $response->withBody("Hello, world!");

        expect($newResponse->body)->toBe("Hello, world!");
        // Original response remains unchanged.
        expect($response->body)->toBeNull();
    });

    it("withHeaders replaces headers entirely", function () {
        $response = new Response();
        $newResponse = $response->withHeaders(["Content-Type" => "text/html"]);

        expect($newResponse->headers)->toBe(["Content-Type" => "text/html"]);
        // Original response remains unchanged.
        expect($response->headers)->toBe([]);
    });

    it("addHeaders merges headers with existing ones", function () {
        $response = new Response();
        // Set some initial headers.
        $responseWithHeaders = $response->withHeaders(["Content-Type" => "text/html"]);
        // Add additional header.
        $newResponse = $responseWithHeaders->addHeaders(["X-Custom" => "Value"]);

        expect($newResponse->headers)->toBe(["Content-Type" => "text/html", "X-Custom" => "Value"]);

        // Test merging when a header key already exists (the new value overwrites the old).
        $mergedResponse = $responseWithHeaders->addHeaders(["Content-Type" => "application/json"]);
        expect($mergedResponse->headers)->toBe(["Content-Type" => "application/json"]);
    });

    it("chains response modifications immutably", function () {
        $response = new Response();
        $newResponse = $response
            ->withStatus(404)
            ->withBody("Error")
            ->withHeaders(["Content-Type" => "text/plain"])
            ->addHeaders(["X-Extra" => "Value"]);

        expect($newResponse->status)->toBe(404);
        expect($newResponse->body)->toBe("Error");
        expect($newResponse->headers)->toBe(["Content-Type" => "text/plain", "X-Extra" => "Value"]);

        // Ensure the original response remains unmodified.
        expect($response->status)->toBe(200);
        expect($response->body)->toBeNull();
        expect($response->headers)->toBe([]);
    });

    it("send outputs the body and returns true", function () {
        $response = new Response();
        $response = $response->withBody("Test Body");

        // Start output buffering to capture the echoed body.
        ob_start();
        $result = $response->send();
        $output = ob_get_clean();

        expect($result)->toBeTrue();
        expect($output)->toBe("Test Body");
    });

    it("send with detachResponse outputs the body and returns true", function () {
        $response = new Response();
        $response = $response->withBody("Detached Body");

        ob_start();
        $result = $response->send(true);
        $output = ob_get_clean();

        expect($result)->toBeTrue();
        expect($output)->toBe("Detached Body");
    });
});
