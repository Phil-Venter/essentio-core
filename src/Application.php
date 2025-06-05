<?php

namespace Essentio\Core;

use Throwable;

class Application
{
    public static string $basePath;

    public static Container $container;

    public static function http(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();

        static::$container->once(Environment::class);
        static::$container->once(Session::class, Session::create(...));
        static::$container->once(Request::class, Request::create(...));
        static::$container->once(Response::class);
        static::$container->once(Router::class);

        $env = static::$container->resolve(Environment::class);
        $env->load(static::fromBase(".env"));

        static::$container->once(
            Jwt::class,
            fn(?string $secret = null): Jwt => new Jwt($secret ?? $env->get("JWT_SECRET", "Essentio"))
        );
    }

    public static function cli(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();

        static::$container->once(Environment::class);
        static::$container->once(Argument::class, Argument::create(...));

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));
    }

    public static function fromBase(string $path): string
    {
        return static::$basePath . "/" . ltrim($path, "/");
    }

    public static function run(): void
    {
        $request = static::$container->resolve(Request::class);
        $response = static::$container->resolve(Response::class);

        try {
            static::$container->resolve(Router::class)->dispatch($request, $response)->send();
        } catch (HttpException $e) {
            $status = $e->getCode() ?: 500;
            $response->setStatus($status)->setBody($e->getMessage())->send();
        } catch (Throwable) {
            $response->setStatus(500)->setBody("Internal Server Error")->send();
        }
    }
}
