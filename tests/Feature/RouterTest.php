<?php

use Essentio\Core\Router;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\HttpException;

function createRequest(string $method, string $path): Request
{
    return new Request(
        method: $method,
        port: 80,
        path: trim($path, "/"),
        query: [],
        contentType: "text/plain",
        headers: [],
        cookies: [],
        files: [],
        body: [],
        parameters: []
    );
}

function createResponse(): Response
{
    return new Response();
}

it("matches static route and sends response", function () {
    $router = new Router();

    $router->add("GET", "hello/world", fn() => createResponse()->setBody("Hello"));

    $req = createRequest("GET", "hello/world");
    $res = $router->dispatch($req, createResponse());

    ob_start();
    $res->send();
    $output = ob_get_clean();

    expect($output)->toBe("Hello");
});

it("matches parameterized route and sends id", function () {
    $router = new Router();

    $router->add("GET", "users/:id", fn(Request $r) => createResponse()->setBody($r->parameters["id"]));

    $req = createRequest("GET", "users/123");
    $res = $router->dispatch($req, createResponse());

    ob_start();
    $res->send();
    $output = ob_get_clean();

    expect($output)->toBe("123");
});

it("wraps scalar return into Response body", function () {
    $router = new Router();

    $router->add("GET", "text", fn() => "hello text");

    $req = createRequest("GET", "text");
    $res = $router->dispatch($req, createResponse());

    ob_start();
    $res->send();
    $output = ob_get_clean();

    expect($output)->toBe("hello text");
});

it("wraps Stringable return into Response body", function () {
    $router = new Router();

    $router->add(
        "GET",
        "stringable",
        fn() => new class {
            public function __toString(): string
            {
                return "stringable content";
            }
        }
    );

    $req = createRequest("GET", "stringable");
    $res = $router->dispatch($req, createResponse());

    ob_start();
    $res->send();
    $output = ob_get_clean();

    expect($output)->toBe("stringable content");
});

it("throws 204 if result is null or empty", function () {
    $router = new Router();

    $router->add("GET", "empty", fn() => null);

    $req = createRequest("GET", "empty");

    expect(fn() => $router->dispatch($req, createResponse()))->toThrow(HttpException::class)->and(fn($e) => expect($e->getCode())->toBe(204));
});

it("throws 404 if path not matched", function () {
    $router = new Router();

    $router->add("GET", "found", fn() => "yes");

    $req = createRequest("GET", "not-found");

    expect(fn() => $router->dispatch($req, createResponse()))->toThrow(HttpException::class)->and(fn($e) => expect($e->getCode())->toBe(404));
});

it("throws 405 if method not matched", function () {
    $router = new Router();

    $router->add("GET", "only/get", fn() => "nope");

    $req = createRequest("POST", "only/get");

    expect(fn() => $router->dispatch($req, createResponse()))->toThrow(HttpException::class)->and(fn($e) => expect($e->getCode())->toBe(405));
});

it("applies global middleware in correct order", function () {
    $router = new Router();
    $trace = [];

    $router->middleware(function (Request $r, callable $next) use (&$trace) {
        $trace[] = "before";
        $res = $next($r);
        $trace[] = "after";
        return $res;
    });

    $router->add("GET", "mw", fn() => "ok");

    $req = createRequest("GET", "mw");
    $res = $router->dispatch($req, createResponse());

    ob_start();
    $res->send();
    ob_end_clean();

    expect($trace)->toBe(["before", "after"]);
});

it("applies route-specific middleware", function () {
    $router = new Router();
    $trace = [];

    $router->add("GET", "route/mw", fn() => "done")->middleware(function (Request $r, callable $next) use (&$trace) {
        $trace[] = "route-mw";
        return $next($r);
    });

    $req = createRequest("GET", "route/mw");
    $res = $router->dispatch($req, createResponse());

    ob_start();
    $res->send();
    ob_end_clean();

    expect($trace)->toBe(["route-mw"]);
});

it("builds named route URL", function () {
    $router = new Router();

    $router->add("GET", "users/:id", fn() => "irrelevant")->name("user.view");

    $url = $router->makeUrlByName("user.view", ["id" => 7]);

    expect($url)->toBe("/users/7");
});

it("throws for unknown named route", function () {
    $router = new Router();

    expect(fn() => $router->makeUrlByName("missing", []))->toThrow(InvalidArgumentException::class);
});

it("throws if named route param missing", function () {
    $router = new Router();

    $router->add("GET", "articles/:slug", fn() => "")->name("article.view");

    expect(fn() => $router->makeUrlByName("article.view", []))->toThrow(InvalidArgumentException::class);
});

it("adds query string for extra params in named route", function () {
    $router = new Router();

    $router->add("GET", "search/:term", fn() => "")->name("search");

    $url = $router->makeUrlByName("search", ["term" => "cat", "page" => 3]);

    expect($url)->toBe("/search/cat?page=3");
});
