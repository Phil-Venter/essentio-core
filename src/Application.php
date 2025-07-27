<?php
declare(strict_types=1);

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
     * Bootstrap the application for CLI mode.
     */
    public static function cli(string $basePath): void
    {
        static::$basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(static::fromBase('.env')));

        if (class_exists(Argument::class)) {
            Container::instance()->once(Argument::class, fn(): Argument => Argument::create());
        }
    }

    /**
     * Bootstrap the application for HTTP mode.
     */
    public static function http(string $basePath): void
    {
        static::$basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(static::fromBase('.env')));

        if (class_exists(Request::class)) {
            Container::instance()->once(Request::class, fn(): Request => Request::create());
        }

        if (class_exists(Response::class)) {
            Container::instance()->once(Response::class);
        }

        if (class_exists(Router::class)) {
            Container::instance()->once(Router::class);
        }

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
        return static::$basePath . ltrim($path, DIRECTORY_SEPARATOR);
    }

    /**
     * Autoloading without composer required.
     */
    public static function autoload(array $registry): void
    {
        foreach ($registry as $prefix => $path) {
            // Immediate inclusion for individual files
            if (is_file($path)) {
                if (is_readable($path)) {
                    require_once $path;
                }

                continue;
            }

            // Normalize prefix and base directory
            $prefix = rtrim($prefix, '\\') . '\\';
            $length = strlen($prefix);
            $path = rtrim((string) $path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

            // Register class autoloader
            spl_autoload_register(function ($class) use ($prefix, $length, $path): void {
                if (strncmp($prefix, $class, $length) !== 0) {
                    return;
                }

                $file = $path . str_replace('\\', DIRECTORY_SEPARATOR, substr($class, $length)) . '.php';
                if (is_file($file) && is_readable($file)) {
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

        try {
            $request = Container::instance()->get(Request::class);
            $response = Container::instance()->get(Response::class);
        } catch (Throwable) {
            http_response_code(500);
            echo 'Initialization Error';
            return;
        }

        try {
            Container::instance()->get(Router::class)->dispatch($request, $response)->send();
        } catch (Throwable $throwable) {
            if (class_exists(HttpException::class) && is_a($throwable, HttpException::class)) {
                $status = $throwable->getCode() ?: 500;
                $response->setStatus($status)->setBody($throwable->getMessage())->send();
            } else {
                error_log($throwable->getMessage());
                $response->setStatus(500)->setBody("Internal Server Error")->send();
            }
        }
    }
}
