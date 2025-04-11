<?php

namespace Essentio\Core;

use RuntimeException;
use Throwable;

use function realpath;
use function rtrim;
use function session_start;
use function session_status;
use function sprintf;

/**
 * Handles the initialization and execution of the application for both
 * web and CLI environments.
 */
class Application
{
    /** @var string */
    protected static string $basePath;

    /** @var Container */
    public static Container $container;

    /** @var bool */
    public static bool $isWeb;

    /**
     * Initialize the application for HTTP requests.
     *
     * Sets up the container bindings for web-specific components,
     * and ensures that session handling is configured.
     *
     * @param string $basePath
     * @return void
     */
    public static function http(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();
        static::$isWeb = true;

        static::$container->bind(Environment::class, fn() => new Environment())->once();
        static::$container->bind(Request::class, fn() => Request::init())->once();
        static::$container->bind(Router::class, fn() => new Router())->once();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Initialize the application for CLI commands.
     *
     * Sets up the container bindings for CLI-specific components.
     *
     * @param string $basePath
     * @return void
     */
    public static function cli(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();
        static::$isWeb = false;

        static::$container->bind(Environment::class, fn() => new Environment())->once();
        static::$container->bind(Argument::class, fn() => Argument::init())->once();
    }

    /**
     * Resolve an absolute path based on the application's base directory.
     *
     * This method concatenates the stored base path with the provided relative path,
     * then uses `realpath()` to return the absolute path. If the path cannot be resolved,
     * it returns false.
     *
     * @param string $path
     * @return string|false
     */
    public static function fromBase(string $path): string|false
    {
        $path = sprintf("%s/%s", static::$basePath, $path);
        return realpath($path) ?: $path;
    }

    /**
     * Run the application.
     *
     * For web applications, it processes the request using the Router and
     * sends the corresponding response. In case of an exception, a 500 error
     * is returned.
     *
     * @return void
     */
    public static function run(): void
    {
        if (!static::$isWeb) {
            return;
        }

        try {
            static::$container
                ->get(Router::class)
                ->dispatch(static::$container->get(Request::class))
                ->send();
        } catch (HttpException $e) {
            (new Response())
                ->withStatus($e->getCode())
                ->withHeaders(["Content-Type" => "text/html"])
                ->withBody($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            (new Response())
                ->withStatus(500)
                ->withHeaders(["Content-Type" => "text/plain"])
                ->withBody("Something went wrong. Please try again later.")
                ->send();
        }
    }
}
