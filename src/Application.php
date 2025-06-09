<?php

namespace Essentio\Core;

use Essentio\Core\Extra\{Authenticated, Cast, Mailer, Query, Validate};
use PDO;
use Throwable;

class Application
{
    public static string $basePath;

    public static Container $container;

    public static function http(string $basePath): void
    {
        static::initCommon($basePath);

        static::$container->once(Session::class, Session::create(...));
        static::$container->once(Jwt::class, Jwt::create(...));
        static::$container->once(Request::class, Request::create(...));
        static::$container->once(Response::class);
        static::$container->once(Router::class);

        if (class_exists(Authenticated::class, true)) {
            static::$container->once(Authenticated::class, Authenticated::create(...));
        }

        if (class_exists(Cast::class, true)) {
            static::$container->once(Cast::class);
        }

        if (class_exists(Validate::class, true)) {
            static::$container->once(Validate::class);
        }
    }

    public static function cli(string $basePath): void
    {
        static::initCommon($basePath);
        static::$container->once(Argument::class, Argument::create(...));
    }

    protected static function initCommon(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();

        static::$container->once(Environment::class);
        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));

        static::$container->once(PDO::class, function (
            ?string $dsn = null,
            ?string $user = null,
            ?string $pass = null
        ) {
            $env = static::$container->resolve(Environment::class);
            return new PDO($dsn ?? $env->get("DB_DSN"), $user ?? $env->get("DB_USER"), $pass ?? $env->get("DB_PASS"));
        });

        if (class_exists(Mailer::class, true)) {
            static::$container->bind(Mailer::class, Mailer::create(...));
        }

        if (class_exists(Query::class, true)) {
            static::$container->bind(Query::class, Query::create(...));
        }
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
