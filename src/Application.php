<?php

namespace Essentio\Core;

use Throwable;

use function rtrim;
use function sprintf;

class Application
{
    /** @var string */
    protected static string $basePath;

    /** @var string */
    protected static string $contentType = "";

    /** @var bool */
    protected static bool $isHttp;

    /** @var Container */
    public static Container $container;

    /**
     * Bootstraps an API context (application/json), binds required services and config.
     *
     * @param string      $basePath Absolute project base path.
     * @param string|null $secret   Optional JWT secret (overrides env JWT_SECRET).
     * @return void
     */
    public static function api(string $basePath, ?string $secret = null): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$contentType = "application/json";
        static::$isHttp = true;

        static::$container = new Container();

        static::$container->once(Environment::class, fn(): Environment => new Environment());
        static::$container->once(Request::class, fn(): Request => Request::new());
        static::$container->once(Router::class, fn(): Router => new Router());

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));

        $secret ??= static::$container->resolve(Environment::class)->get("JWT_SECRET", "Essentio");
        static::$container->once(JWT::class, fn(): JWT => new JWT($secret));
    }

    /**
     * Checks if app is in API (application/json) context.
     *
     * @return bool True if API mode is active.
     */
    public static function isApi(): bool
    {
        return static::$isHttp && static::$contentType === "application/json";
    }

    /**
     * Initializes CLI environment, loading env config and command-line parser.
     *
     * @param string $basePath Base directory for resolving paths.
     * @return void
     */
    public static function cli(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$isHttp = false;

        static::$container = new Container();

        static::$container->once(Environment::class, fn(): Environment => new Environment());
        static::$container->once(Argument::class, fn(): Argument => Argument::new());

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));
    }

    /**
     * Determines if app is running in CLI mode.
     *
     * @return bool True if CLI.
     */
    public static function isCli(): bool
    {
        return !static::$isHttp;
    }

    /**
     * Bootstraps an HTML web context with sessions, routing, and environment.
     *
     * @param string $basePath Root directory of the project.
     * @return void
     */
    public static function web(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$contentType = "text/html";
        static::$isHttp = true;

        static::$container = new Container();

        static::$container->once(Environment::class, fn(): Environment => new Environment());
        static::$container->once(Session::class, fn(): Session => new Session());
        static::$container->once(Request::class, fn(): Request => Request::new());
        static::$container->once(Router::class, fn(): Router => new Router());

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));
    }

    /**
     * Indicates if current context is standard web (HTML) application.
     *
     * @return bool True if web mode.
     */
    public static function isWeb(): bool
    {
        return static::$isHttp && static::$contentType === "text/html";
    }

    /**
     * Returns an absolute path by joining the base path with the given relative path.
     *
     * @param string $path Relative file or folder path.
     * @return string Absolute filesystem path.
     */
    public static function fromBase(string $path): string
    {
        return sprintf("%s/%s", static::$basePath, $path);
    }

    /**
     * Entry point for handling HTTP requests.
     * Routes the request, invokes handlers, and generates a response.
     *
     * Handles HTTP errors and internal exceptions gracefully.
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
        } catch (Throwable $throwable) {
            error_log(sprintf("[%s]\n%s", $throwable->getMessage(), $throwable->getTraceAsString()));

            static::$container
                ->resolve(Response::class)
                ->withStatus($throwable instanceof HttpException ? $throwable->getCode() : 500)
                ->withHeaders(["Content-Type" => static::$contentType])
                ->withBody($throwable instanceof HttpException ? $throwable->getMessage() : "Something went wrong.")
                ->send();
        }
    }
}
