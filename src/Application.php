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

    /** @var string */
    protected static string $webContentType = "";

    /** @var bool */
    protected static bool $isWeb;

    /** @var Container */
    public static Container $container;

    /**
     * Initialize the application for API requests.
     *
     * @param string $basePath
     * @param ?string $secret
     * @return void
     */
    public static function api(string $basePath, ?string $secret = null): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$webContentType = "application/json";
        static::$isWeb = true;

        static::$container = new Container();

        static::$container->bind(Environment::class, fn(): Environment => new Environment())->once = true;
        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));

        $secret ??= static::$container->resolve(Environment::class)->get("JWT_SECRET", "Essentio");
        static::$container->bind(JWT::class, fn($c): JWT => new JWT($secret))->once = true;
        static::$container->bind(Request::class, fn(): Request => Request::new())->once = true;
        static::$container->bind(Router::class, fn(): Router => new Router())->once = true;
    }

    /**
     * Initialize the application for HTTP requests.
     *
     * @param string $basePath
     * @return void
     */
    public static function http(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$webContentType = "text/html";
        static::$isWeb = true;

        static::$container = new Container();

        static::$container->bind(Environment::class, fn(): Environment => new Environment())->once = true;
        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));

        static::$container->bind(Session::class, fn(): Session => new Session())->once = true;
        static::$container->bind(Request::class, fn(): Request => Request::new())->once = true;
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
        static::$isWeb = false;

        static::$container = new Container();

        static::$container->bind(Environment::class, fn(): Environment => new Environment())->once = true;
        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));

        static::$container->bind(Argument::class, fn(): Argument => Argument::new())->once = true;
    }

    public static function isApi(): bool
    {
        return static::$isWeb && static::$webContentType === "application/json";
    }

    public static function isCli(): bool
    {
        return !static::$isWeb;
    }

    public static function isWeb(): bool
    {
        return static::$isWeb && static::$webContentType === "text/html";
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
        if (static::isCli()) {
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
                ->withHeaders(["Content-Type" => static::$webContentType])
                ->withBody($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            error_log(sprintf("[%s]\n%s", $e->getMessage(), $e->getTraceAsString()));
            new Response()
                ->withStatus(500)
                ->withHeaders(["Content-Type" => static::$webContentType])
                ->withBody("Something went wrong. Please try again later.")
                ->send();
        }
    }
}
