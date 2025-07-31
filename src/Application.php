<?php

namespace Essentio;

use Essentio\Api\Jwt;
use Essentio\Cli\Argument;
use Essentio\Http\HttpException;
use Essentio\Http\Request;
use Essentio\Http\Response;
use Essentio\Http\Router;
use Essentio\Web\Session;
use Throwable;

/**
 * @api
 */
class Application
{
    public static string $basePath;

    /**
     * Bootstrap the application with minimal dependancies.
     */
    public static function base(string $basePath): void
    {
        static::$basePath = rtrim($basePath, '/') . '/';

        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(static::fromBase('.env')));
    }

    /**
     * Bootstrap the application for CLI mode.
     */
    public static function cli(string $basePath): void
    {
        static::base($basePath);

        Container::instance()->once(Argument::class, fn(): Argument => Argument::create());
    }

    /**
     * Bootstrap the application for HTTP mode.
     */
    public static function http(string $basePath): void
    {
        static::base($basePath);

        Container::instance()->once(Request::class, fn(): Request => Request::create());
        Container::instance()->once(Response::class);
        Container::instance()->once(Router::class);

        if (class_exists(Jwt::class)) {
            Container::instance()->once(Jwt::class, fn(): Jwt => Jwt::create(Container::instance()->get(Environment::class)));
        }

        if (class_exists(Session::class)) {
            Container::instance()->once(Session::class, fn(): Session => Session::create());
        }
    }

    /**
     * Return full path from the $basePath passed into the cli() or http() factory.
     */
    public static function fromBase(string $path): string
    {
        return static::$basePath . ltrim($path, '/');
    }

    /**
     * Optimistic PSR-4 style autloading
     */
    public static function autoload(array $registry): void
    {
        foreach ($registry as $prefix => $path) {
            $prefix = trim($prefix, '\\') . '\\';
            $path = rtrim((string) $path, '/\\') . '/';

            if (is_file($path)) {
                if (is_readable($path)) {
                    require_once $path;
                }

                continue;
            }

            spl_autoload_register(function (string $class) use ($prefix, $path): void {
                if (!str_starts_with($class, $prefix)) {
                    return;
                }

                $relative = substr($class, strlen($prefix));
                $file = $path . str_replace('\\', '/', $relative) . '.php';

                if (is_readable($file)) {
                    require_once $file;
                }
            });
        }
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
        } catch (Throwable $throwable) {
            if (class_exists(HttpException::class) && is_a($throwable, HttpException::class)) {
                $response->setStatus($throwable->getCode() ?: 500)->setBody($throwable->getMessage())->send();
            } else {
                error_log($throwable->getMessage());
                $response->setStatus(500)->setBody("Internal Server Error")->send();
            }
        }
    }
}
