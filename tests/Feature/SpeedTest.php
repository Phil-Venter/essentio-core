<?php

use Essentio\Core\Application;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\Router;

beforeEach(function () {
    Application::http(__DIR__ . "/../../");
});

test("router dispatches from among 100 routes within acceptable time", function () {
    $router = app(Router::class);

    // Register 100 routes
    for ($i = 0; $i < 100; $i++) {
        $router->add("GET", "test/$i/:hi", function (Request $request, Response $response) {
            return $response->withBody("Route " . $request->get("hi"));
        });
    }

    // Choose one route at random
    $randomIndex = random_int(0, 99);
    $uri = "/test/$randomIndex/named";

    // Create simulated request
    $request = Request::init([
        "REQUEST_METHOD" => "GET",
        "REQUEST_URI" => $uri,
        "HTTP_HOST" => "localhost",
    ]);

    // Time the dispatch
    $start = hrtime(true);
    $response = $router->dispatch($request);
    $end = hrtime(true);

    $durationMs = ($end - $start) / 1e6;

    // Validate response and timing
    expect($response)->toBeInstanceOf(Response::class);
    ob_start();
    $response->send();
    expect(ob_get_clean())->toContain("Route named");
    expect($durationMs)->toBeLessThan(0.01);
});
