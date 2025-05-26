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
    protected static string $contentType = "";

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
        static::$contentType = "application/json";
        static::$isWeb = true;

        static::$container = new Container();

        static::$container->bind(Environment::class, fn(): Environment => new Environment())->once = true;
        static::$container->bind(Request::class, fn(): Request => Request::new())->once = true;
        static::$container->bind(Router::class, fn(): Router => new Router())->once = true;

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));

        $secret ??= static::$container->resolve(Environment::class)->get("JWT_SECRET", "Essentio");
        static::$container->bind(JWT::class, fn($c): JWT => new JWT($secret))->once = true;
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
        static::$container->bind(Argument::class, fn(): Argument => Argument::new())->once = true;

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));
    }

    /**
     * Initialize the application for HTTP requests.
     *
     * @param string $basePath
     * @return void
     */
    public static function web(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$contentType = "text/html";
        static::$isWeb = true;

        static::$container = new Container();

        static::$container->bind(Environment::class, fn(): Environment => new Environment())->once = true;
        static::$container->bind(Session::class, fn(): Session => new Session())->once = true;
        static::$container->bind(Request::class, fn(): Request => Request::new())->once = true;
        static::$container->bind(Router::class, fn(): Router => new Router())->once = true;

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));
    }

    /**
     * Indicates whether the application is running in API mode.
     *
     * @return bool True if in API mode, false otherwise.
     */
    public static function isApi(): bool
    {
        return static::$isWeb && static::$contentType === "application/json";
    }

    /**
     * Indicates whether the application is running in CLI mode.
     *
     * @return bool True if in CLI mode, false otherwise.
     */
    public static function isCli(): bool
    {
        return !static::$isWeb;
    }

    /**
     * Indicates whether the application is running in web (HTML) mode.
     *
     * @return bool True if in web mode, false otherwise.
     */
    public static function isWeb(): bool
    {
        return static::$isWeb && static::$contentType === "text/html";
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
     * Executes the router and sends a response, handling exceptions appropriately.
     *
     * Only used in web or API contexts; has no effect in CLI mode.
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
                ->withHeaders(["Content-Type" => static::$contentType])
                ->withBody($e->getMessage())
                ->send();
        } catch (Throwable $e) {
            error_log(sprintf("[%s]\n%s", $e->getMessage(), $e->getTraceAsString()));
            new Response()
                ->withStatus(500)
                ->withHeaders(["Content-Type" => static::$contentType])
                ->withBody("Something went wrong. Please try again later.")
                ->send();
        }
    }
}
