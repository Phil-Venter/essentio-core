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

it("ignores external entities in XML payloads", function () {
    $server = fakeServer([
        "REQUEST_METHOD" => "POST",
        "REQUEST_URI" => "/xml",
    ]);
    $headers = ["Content-Type" => "application/xml"];
    $body = <<<'XML'
    <?xml version="1.0"?>
    <!DOCTYPE data [<!ENTITY ext SYSTEM "file:///etc/passwd">]>
    <root><a>&ext;</a></root>
    XML;

    $req = Request::create($server, $headers, [], [], [], [], $body);

    expect($req->input('a'))->not()->toBeString();
});
