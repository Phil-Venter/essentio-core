<?php

use Essentio\Http\Request;

function fakeServer(array $overrides = []): array
{
    return array_merge(
        [
            "REQUEST_METHOD" => "GET",
            "HTTP_HOST" => "localhost",
            "SERVER_PORT" => 80,
            "REQUEST_URI" => "/api/test?foo=bar",
            "HTTPS" => "",
        ],
        $overrides
    );
}

it("parses GET request with query", function () {
    $server = fakeServer();
    $headers = ["Content-Type" => "application/json"];
    $query = ["foo" => "bar"];

    $req = Request::create($server, $headers, $query);

    expect($req->method)->toBe("GET");
    expect($req->path)->toBe("api/test");
    expect($req->port)->toBe(80);
    expect($req->get("foo"))->toBe("bar");
    expect($req->contentType)->toBe("application/json");
});

it("parses POST with urlencoded form body", function () {
    $server = fakeServer(["REQUEST_METHOD" => "POST", "REQUEST_URI" => "/submit"]);
    $headers = ["Content-Type" => "application/x-www-form-urlencoded"];
    $post = ["username" => "admin"];

    $req = Request::create($server, $headers, [], $post);

    expect($req->method)->toBe("POST");
    expect($req->input("username"))->toBe("admin");
});

it("parses raw JSON body", function () {
    $server = fakeServer(["REQUEST_METHOD" => "POST", "REQUEST_URI" => "/data"]);
    $headers = ["Content-Type" => "application/json"];
    $body = json_encode(["key" => "value"]);

    $req = Request::create($server, $headers, [], [], [], [], $body);

    expect($req->input("key"))->toBe("value");
});

it("uses _method override from POST data", function () {
    $server = fakeServer(["REQUEST_METHOD" => "POST", "REQUEST_URI" => "/update"]);
    $headers = ["Content-Type" => "application/x-www-form-urlencoded"];
    $post = ["_method" => "PUT", "value" => "x"];

    $req = Request::create($server, $headers, [], $post);

    expect($req->method)->toBe("PUT");
    expect($req->input("value"))->toBe("x");
});

it("normalizes files array into flat structure", function () {
    $server = fakeServer(["REQUEST_METHOD" => "POST"]);
    $headers = ["Content-Type" => "multipart/form-data"];
    $files = [
        "upload" => [
            "name" => ["file.txt"],
            "type" => ["text/plain"],
            "tmp_name" => ["/tmp/php123"],
            "error" => [UPLOAD_ERR_OK],
            "size" => [123],
        ],
    ];

    $req = Request::create($server, $headers, [], [], [], $files);

    expect($req->files)->toHaveCount(1);
    expect($req->files[0]["name"])->toBe("file.txt");
    expect($req->files[0]["error"])->toBe(UPLOAD_ERR_OK);
});
