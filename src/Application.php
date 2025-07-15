<?php

namespace Essentio\Core;

use Throwable;

/**
 * @api
 */
class Application
{
    /**
     * Bootstrap the application for CLI mode.
     */
    public static function cli(string $basePath): void
    {
        $container = Container::instance();

        $container->once(Argument::class, fn(): Argument => Argument::create($container->get(Helper::class)));
        $container->once(Environment::class, fn(): Environment => Environment::create($container->get(Helper::class)));
        $container->once(Helper::class, fn(): Helper => Helper::create($basePath));
    }

    /**
     * Bootstrap the application for HTTP mode.
     */
    public static function http(string $basePath): void
    {
        $container = Container::instance();

        $container->once(Environment::class, fn(): Environment => Environment::create($container->get(Helper::class)));
        $container->once(Helper::class, fn(): Helper => Helper::create($basePath));
        $container->once(Jwt::class, fn(): Jwt => Jwt::create($container->get(Environment::class)));
        $container->once(Request::class, fn(): Request => Request::create());
        $container->once(Response::class);
        $container->once(Router::class);
        $container->once(Session::class, fn(): Session => Session::create());
    }

    /**
     * Run the application and handle the request.
     */
    public static function run(): void
    {
        if (PHP_SAPI === "cli") {
            exit(1);
        }

        $container = Container::instance();
        $request = $container->get(Request::class);
        $response = $container->get(Response::class);

        try {
            $container->get(Router::class)->dispatch($request, $response)->send();
        } catch (HttpException $e) {
            $status = $e->getCode() ?: 500;
            $response->setStatus($status)->setBody($e->getMessage())->send();
        } catch (Throwable) {
            $response->setStatus(500)->setBody("Internal Server Error")->send();
        }
    }
}
