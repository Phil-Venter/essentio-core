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
        Container::instance()->once(Argument::class, fn(): Argument => Argument::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Helper::class, fn(): Helper => Helper::create($basePath));
    }

    /**
     * Bootstrap the application for HTTP mode.
     */
    public static function http(string $basePath): void
    {
        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Helper::class, fn(): Helper => Helper::create($basePath));
        Container::instance()->once(Jwt::class, fn(): Jwt => Jwt::create(Container::instance()->get(Environment::class)));
        Container::instance()->once(Request::class, fn(): Request => Request::create());
        Container::instance()->once(Response::class);
        Container::instance()->once(Session::class, fn(): Session => Session::create());
    }

    /**
     * Run the application and handle the request.
     */
    public static function run(): void
    {
        if (PHP_SAPI === "cli") {
            exit(1);
        }

        $request = Container::instance()->get(Request::class);
        $response = Container::instance()->get(Response::class);

        try {
            Container::instance()->get(Router::class)->dispatch($request, $response)->send();
        } catch (HttpException $e) {
            $status = $e->getCode() ?: 500;
            $response->setStatus($status)->setBody($e->getMessage())->send();
        } catch (Throwable) {
            $response->setStatus(500)->setBody("Internal Server Error")->send();
        }
    }
}
