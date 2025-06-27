<?php

namespace Essentio\Core;

use Throwable;

class Application
{
    public static function cli(string $basePath): void
    {
        Container::instance()->once(Argument::class, fn() => Argument::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Environment::class, fn() => Environment::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Helper::class, fn() => Helper::create($basePath));
    }

    public static function http(string $basePath): void
    {
        Container::instance()->once(Environment::class, fn() => Environment::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Helper::class, fn() => Helper::create($basePath));
        Container::instance()->once(Jwt::class, fn() => Jwt::create(Container::instance()->get(Environment::class)));
        Container::instance()->once(Request::class, fn() => Request::create());
        Container::instance()->once(Response::class);
        Container::instance()->once(Session::class, fn() => Session::create());
    }

    public static function run(): void
    {
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
