<?php

namespace Essentio\Core;

use Throwable;

use function error_log;
use function rtrim;
use function sprintf;

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
     * @param string $basePath
     * @return void
     */
    public static function http(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();
        static::$isWeb = true;

        static::$container->bind(Environment::class, fn(): Environment => new Environment())->once = true;
        static::$container->bind(Session::class, fn(): Session => new Session())->once = true;
        static::$container->bind(Request::class, fn(): Request => Request::init())->once = true;
        static::$container->bind(Router::class, fn(): Router => new Router())->once = true;
    }

    /**
     * Initialize the application for CLI commands.
     *
     * @param string $basePath
     * @return void
     */
    public static function cli(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();
        static::$isWeb = false;

        static::$container->bind(Environment::class, fn(): Environment => new Environment())->once = true;
        static::$container->bind(Argument::class, fn(): Argument => Argument::init())->once = true;
    }

    /**
     * Resolve an absolute path based on the application's base directory.
     *
     * @param string $path
     * @return string
     */
    public static function fromBase(string $path): string
    {
        return sprintf("%s/%s", static::$basePath, $path);
    }

    /**
     * Run the application, not required for cli.
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
                ->resolve(Router::class)
                ->dispatch(static::$container->resolve(Request::class))
                ->send();
        } catch (HttpException $e) {
            new Response()
                ->withStatus($e->getCode())
                ->withHeaders(["Content-Type" => "text/html"])
                ->withBody($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            error_log(sprintf("[%s]\n%s", $e->getMessage(), $e->getTraceAsString()));

            new Response()
                ->withStatus(500)
                ->withHeaders(["Content-Type" => "text/plain"])
                ->withBody("Something went wrong. Please try again later.")
                ->send();
        }
    }
}
