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
        static::$container->once(Jwt::class, fn(?string $secret = null): \Essentio\Core\Jwt => new Jwt($secret ?? "Essentio"));
        static::$container->once(Request::class, [Request::class, "create"]);
        static::$container->once(Response::class);
        static::$container->once(Router::class);

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));
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
        $response = static::$container->resolve(Response::class);

        try {
            static::$container
                ->resolve(Router::class)
                ->dispatch(static::$container->resolve(Request::class), $response)
                ->send();
        } catch (Throwable $throwable) {
            $response
                ->setStatus($throwable instanceof HttpException ? $throwable->getCode() : 500)
                ->setBody($throwable instanceof HttpException ? $throwable->getMessage() : "Internal Server Error")
                ->send();
        }
    }
}
