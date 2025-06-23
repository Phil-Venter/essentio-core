<?php

use Essentio\Core\Application;
use Essentio\Core\HttpException;
use Essentio\Core\Router;

beforeEach(function () {
    $this->basePath = __DIR__;
});

it("handles HttpException correctly in run()", function () {
    Application::http($this->basePath);
    $router = Application::$container->resolve(Router::class);
    $router->add("GET", "test", fn() => throw HttpException::create(404, "Not Found"));

    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["REQUEST_URI"] = "/test";

    ob_start();
    Application::run();
    $output = ob_get_clean();

    expect($output)->toBe("Not Found");
});

it("handles generic exceptions with 500", function () {
    Application::http($this->basePath);
    $router = Application::$container->resolve(Router::class);
    $router->add("GET", "test", fn() => throw new Exception("Unexpected"));

    $_SERVER["REQUEST_METHOD"] = "GET";
    $_SERVER["REQUEST_URI"] = "/test";

    ob_start();
    Application::run();
    $output = ob_get_clean();

    expect($output)->toBe("Internal Server Error");
});
