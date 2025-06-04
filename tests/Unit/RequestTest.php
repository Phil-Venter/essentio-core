<?php

use Essentio\Core\Request;

describe(Request::class, function (): void {
    it("initializes with minimal parameters for a GET request", function (): void {
        $server = [
            "REQUEST_METHOD" => "get",
            "SERVER_NAME" => "example.com",
            "SERVER_PORT" => "8080",
            "REQUEST_URI" => "/path/to/resource?foo=bar",
        ];
        $get = ["param1" => "value1"];
        $cookie = ["session" => "abc123"];
        $files = [];
        $body = "raw input data";
        $headers = [];

        $request = Request::create($server, $headers, $get, null, $cookie, $files, $body);

        expect($request->method)->toBe("GET");
        expect($request->port)->toBe(8080);
        expect($request->path)->toBe("path/to/resource");
        expect($request->query)->toBe($get);
        expect($request->cookies)->toBe($cookie);
        expect($request->files)->toBe($files);
        expect($request->body)->toBe([]);
    });

    it("uses _method override from POST data", function (): void {
        $server = [
            "REQUEST_METHOD" => "POST",
            "SERVER_NAME" => "example.com",
            "REQUEST_URI" => "/submit",
        ];
        $post = ["_method" => "patch"];
        $headers = [];

        $request = Request::create($server, $headers, null, $post);

        expect($request->method)->toBe("PATCH");
    });

    it("setParameters overrides query parameters in get() method", function (): void {
        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/data",
        ];
        $get = ["key" => "from_query", "other" => "value"];
        $headers = [];

        $request = Request::create($server, $headers, $get);

        expect($request->get("key", "default"))->toBe("from_query");

        $request->parameters = ["key" => "custom_value"];
        expect($request->get("key", "default"))->toBe("custom_value");
        expect($request->get("nonexistent", "default"))->toBe("default");
    });

    it("input() returns query parameter for GET requests", function (): void {
        $server = [
            "REQUEST_METHOD" => "GET",
            "REQUEST_URI" => "/search",
        ];
        $get = ["q" => "test"];
        $headers = [];

        $request = Request::create($server, $headers, $get);

        expect($request->input("q", "default"))->toBe("test");
        expect($request->input("missing", "default"))->toBe("default");
    });

    it("input() returns body parameter for non-GET requests with JSON", function (): void {
        $server = [
            "REQUEST_METHOD" => "POST",
            "REQUEST_URI" => "/submit",
        ];

        $body = '{"data":"value"}';
        $headers = ["Content-Type" => "application/json"];

        $request = Request::create($server, $headers, null, null, null, null, $body);

        expect($request->input("data", "default"))->toBe("value");
        expect($request->input("missing", "default"))->toBe("default");
    });

    it("input() returns body parameter for non-GET requests with form data", function (): void {
        $server = [
            "REQUEST_METHOD" => "POST",
            "REQUEST_URI" => "/submit-form",
        ];

        $body = "field1=value1&field2=value2";
        $headers = ["Content-Type" => "application/x-www-form-urlencoded"];

        $request = Request::create($server, $headers, null, null, null, null, $body);

        expect($request->input("field1", "default"))->toBe("value1");
        expect($request->input("field2", "default"))->toBe("value2");
    });
});
