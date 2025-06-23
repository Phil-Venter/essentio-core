<?php

namespace Essentio\Core;

use Throwable;

use function rtrim;

class Application
{
    public static Container $container;

    public static function http(string $basePath): void
    {
        [$container, , $environment] = static::bootstrap($basePath);

        $container->once(Session::class, fn(): Session => Session::create());
        $container->once(Jwt::class, fn(): Jwt => new Jwt($environment->get("JWT_SECRET") ?? "", $environment->get("JWT_ISSUER")));
        $container->once(Request::class, fn(): Request => Request::create());
        $container->once(Response::class);
        $container->once(Router::class);

        static::$container = $container;
    }

    public static function cli(string $basePath): void
    {
        [$container, $helper] = static::bootstrap($basePath);
        $container->once(Argument::class, fn(): Argument => Argument::create($helper));

        static::$container = $container;
    }

    protected static function bootstrap(string $basePath): array
    {
        $container = new Container();
        $helper = new Helper(rtrim($basePath, "/"));
        $environment = Environment::create($helper);

        $container->once(Helper::class, fn(): Helper => $helper);
        $container->once(Environment::class, fn(): Environment => $environment);

        return [$container, $helper, $environment];
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
