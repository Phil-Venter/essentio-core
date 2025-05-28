<?php

class Application
{
    /** @var string */
    protected static string $basePath;

    /** @var string */
    protected static string $contentType = '';

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

class Argument
{
    /** @var string */
    public protected(set) string $command = '';

    /** @var array<int|string, string|int|bool|null> */
    public protected(set) array $arguments = [];

    /**
     * Parses the provided argument vector (or $_SERVER['argv']) and returns an instance.
     *
     * @param list<string>|null $argv Optional array of CLI arguments.
     * @return static Parsed Argument instance.
     */
    public static function new(?array $argv = null): static
    {
        $argv ??= $_SERVER["argv"] ?? [];
        $that = new static;
        array_shift($argv);

        if (empty($argv)) {
            return $that;
        }

        while ($arg = array_shift($argv)) {
            if ($arg === "--") {
                $that->arguments = array_merge($that->arguments, $argv);
                break;
            }

            if (str_starts_with((string) $arg, "--")) {
                $option = substr((string) $arg, 2);

                if (str_contains($option, "=")) {
                    [$key, $value] = explode("=", $option, 2);
                } elseif (isset($argv[0]) && $argv[0][0] !== "-") {
                    $key = $option;
                    $value = array_shift($argv);
                } else {
                    $key = $option;
                    $value = true;
                }

                $that->arguments[$key] = $value;
                continue;
            }

            if ($arg[0] === "-") {
                $key = $arg[1];
                $value = substr((string) $arg, 2);

                if (empty($value)) {
                    if (isset($argv[0]) && $argv[0][0] !== "-") {
                        $value = array_shift($argv);
                    } else {
                        $value = true;
                    }
                }

                $that->arguments[$key] = $value;
                continue;
            }

            if (empty($that->command)) {
                $that->command = $arg;
            } else {
                $that->arguments[] = $arg;
            }
        }

        return $that;
    }

    /**
     * Retrieves a specific argument or option value by key.
     *
     * @param int|string $key     The argument key or index.
     * @param mixed      $default Value to return if not found.
     * @return mixed              Retrieved value or default.
     */
    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
    }
}

class Container
{
    /** @var array<class-string, Closure|class-string|null> */
    protected array $bindings = [];

    /**
     * @template T of object
     * @var array<class-string<T>, T|null>
     */
    protected array $cache = [];

    /**
     * Binds a class or closure to an abstract type.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param Closure():T|class-string<T>|null $concrete
     * @return $this
     * @throws RuntimeException
     */
    public function bind(string $abstract, Closure|string|null $concrete = null): self
    {
        if (
            is_string($concrete) &&
            $abstract !== $concrete &&
            (!class_exists($concrete) || !is_subclass_of($concrete, $abstract))
        ) {
            throw new RuntimeException("Cannot bind [{$abstract}] to [{$concrete}].");
        }

        $this->bindings[$abstract] = $concrete ?? $abstract;
        return $this;
    }

    /**
     * Binds a singleton service to the container.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @param Closure():T|class-string<T>|null $concrete
     * @return $this
     */
    public function once(string $abstract, Closure|string|null $concrete = null): self
    {
        $this->cache[$abstract] = null;
        return $this->bind($abstract, $concrete);
    }

    /**
     * Resolves a service instance from the container.
     *
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param array<mixed> $dependencies Optional constructor arguments.
     * @return T|object Resolved service instance.
     * @throws RuntimeException If the service cannot be instantiated.
     */
    public function resolve(string $abstract, array $dependencies = []): object
    {
        if (!isset($this->bindings[$abstract])) {
            if (class_exists($abstract, true)) {
                return new $abstract(...$dependencies);
            }

            throw new RuntimeException("Service [{$abstract}] is not bound and cannot be instantiated.");
        }

        $once = array_key_exists($abstract, $this->cache);

        if ($once && $this->cache[$abstract] !== null) {
            return $this->cache[$abstract];
        }

        $resolved =
            $this->bindings[$abstract] instanceof Closure
                ? $this->bindings[$abstract](...$dependencies)
                : new ($this->bindings[$abstract])(...$dependencies);

        if ($once) {
            $this->cache[$abstract] = $resolved;
        }

        return $resolved;
    }
}

class Environment
{
    /** @var array<string, mixed> */
    public protected(set) array $data = [];

    /**
     * Loads key-value pairs from a .env file into memory.
     * Supports quoted values and auto type inference.
     *
     * @param string $file Path to .env file.
     * @return static
     */
    public function load(string $file): static
    {
        if (!file_exists($file)) {
            return $this;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            if (trim($line)[0] === "#" || !str_contains($line, "=")) {
                continue;
            }

            [$name, $value] = explode("=", $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (preg_match('/^(["\']).*\1$/', $value)) {
                $value = substr($value, 1, -1);
            } else {
                $lower = strtolower($value);
                $value = match (true) {
                    $lower === "true"  => true,
                    $lower === "false" => false,
                    $lower === "null"  => null,
                    is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
                    default            => $value,
                };
            }

            $this->data[$name] = $value;
        }

        return $this;
    }

    /**
     * Retrieves an environment value by key.
     *
     * @param string $key     Name of the variable.
     * @param mixed  $default Default value if not found.
     * @return mixed          The stored or default value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}

class HttpException extends Exception
{
    /** @var array<int, string> */
    public const HTTP_STATUS = [
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        204 => 'No Content',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        500 => 'Internal Server Error',
    ];

    /**
     * Creates a new instance of HttpException with a standard or custom message.
     *
     * @param int               $status   HTTP status code.
     * @param string|null       $message  Optional custom error message.
     * @param Throwable|null    $previous Optional previous exception for chaining.
     * @return static
     */
    public static function new(int $status, ?string $message = null, ?Throwable $previous = null): static
    {
        return new static($message ?? (static::HTTP_STATUS[$status] ?? "Unknown Error"), $status, $previous);
    }
}

class Jwt
{
    /**
     * @param string $secret Secret key for HMAC signing and verification.
     */
    public function __construct(
        protected string $secret,
    ) {
    }

    /**
     * Encodes a payload array into a JWT.
     *
     * @param array $payload Claims to be embedded in the token.
     * @return string Encoded JWT.
     */
    public function encode(array $payload): string
    {
        $header = ["alg" => "HS256", "typ" => "JWT"];
        $segments = [$this->base64url_encode(json_encode($header)), $this->base64url_encode(json_encode($payload))];
        $signingInput = implode(".", $segments);
        $signature = $this->sign($signingInput);

        $segments[] = $this->base64url_encode($signature);
        return implode(".", $segments);
    }

    /**
     * Decodes and verifies a JWT.
     *
     * @param string $token JWT to be decoded.
     * @return array Decoded payload.
     * @throws Exception If the signature is invalid or token is expired.
     */
    public function decode(string $token): array
    {
        [$header64, $payload64, $signature64] = explode(".", $token);
        $signingInput = "$header64.$payload64";
        $signature = $this->base64url_decode($signature64);

        if (!hash_equals($this->sign($signingInput), $signature)) {
            throw new Exception("Invalid token signature");
        }

        $payload = json_decode($this->base64url_decode($payload64), true);

        if (isset($payload["exp"]) && time() > $payload["exp"]) {
            throw new Exception("Token has expired");
        }

        return $payload;
    }

    /**
     * Encodes data using base64 URL-safe encoding.
     *
     * @param string $data Input data.
     * @return string URL-safe base64 encoded string.
     */
    protected function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * Decodes base64 URL-safe encoded data.
     *
     * @param string $data Encoded string.
     * @return string Decoded data.
     */
    protected function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, "-_", "+/"));
    }

    /**
     * Generates an HMAC-SHA256 signature.
     *
     * @param string $input The data to sign.
     * @return string Binary HMAC signature.
     */
    protected function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}

class Request
{
    /** @var string */
    public protected(set) string $method {
        set => /*(f*/strtoupper($value);
    }

    /** @var string */
    public protected(set) string $scheme {
        set => /*(f*/strtolower($value);
    }

    /** @var ?string */
    public protected(set) ?string $host = null;

    /** @var ?int */
    public protected(set) ?int $port = null;

    /** @var string */
    public protected(set) string $path;

    /** @var array<string,mixed> */
    public protected(set) array $parameters = [];

    /** @var array<string,mixed> */
    public protected(set) array $query;

    /** @var array<string,mixed> */
    public protected(set) array $headers;

    /** @var array<string,mixed> */
    public protected(set) array $cookies;

    /** @var array<string,mixed> */
    public protected(set) array $files;

    /** @var string */
    public protected(set) string $rawInput;

    /** @var array<string,mixed> */
    public protected(set) array $body;

    /** @var array<string,string> */
    public protected(set) array $errors = [];

    /**
     * Initializes and returns a new Request instance using PHP superglobals.
     *
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $headers
     * @param array<string, mixed>|null $get
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookie
     * @param array<string, mixed>|null $files
     * @param string|null               $body
     * @return static
     */
    public static function new(
        ?array $server = null,
        ?array $headers = null,
        ?array $get = null,
        ?array $post = null,
        ?array $cookie = null,
        ?array $files = null,
        ?string $body = null,
    ): static
    {
        $server ??= $_SERVER ?? [];
        $post ??= $_POST ?? [];

        $that = new static();

        $that->method = $post["_method"] ?? $server["REQUEST_METHOD"] ?? "GET";
        $that->scheme = filter_var($server["HTTPS"] ?? "", FILTER_VALIDATE_BOOLEAN) ? "https" : "http";

        $host = null;
        $port = null;

        if (isset($server["HTTP_HOST"])) {
            if (str_contains($server["HTTP_HOST"], ":")) {
                [$host, $port] = explode(":", $server["HTTP_HOST"], 2);
                $port = (int) $port;
            } else {
                $host = $server["HTTP_HOST"];
                $port = $that->scheme === "https" ? 443 : 80;
            }
        }

        $that->host = $host ?? $server["SERVER_NAME"] ?? "localhost";
        $that->port = (int) ($port ?? $server["SERVER_PORT"] ??  80);
        $that->path = trim(parse_url($server["REQUEST_URI"] ?? "", PHP_URL_PATH) ?? "", "/");
        $that->query = $get ?? $_GET ?? [];
        $that->headers = $headers ?? (function_exists("getallheaders") ? (getallheaders() ?: []) : []);
        $that->cookies = $cookie ?? $_COOKIE ?? [];
        $that->files = $files ?? $_FILES ?? [];
        $that->rawInput = $body ?? file_get_contents("php://input") ?: "";

        $mimeType = explode(";", $that->headers["Content-Type"] ?? "", 2)[0] ?? "";

        $that->body = match ($mimeType) {
            "application/x-www-form-urlencoded" => (function (string $input): array {
                parse_str($input, $result);
                return $result;
            })($that->rawInput),
            "application/json" => json_decode($that->rawInput, true),
            "application/xml", "text/xml" => (function (string $input): array {
                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($input);
                return $xml ? json_decode(json_encode($xml), true) : [];
            })($that->rawInput),
            default => $post,
        };

        return $that;
    }

    /**
     * Sets custom parameters for the request.
     *
     * @param array<string, mixed> $parameters
     * @return static
     */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Retrieve a value from the request parameters.
     *
     * @param string $field
     * @param mixed  $default
     * @return mixed
     */
    public function get(string $field, mixed $default = null): mixed
    {
        return $this->parameters[$field] ?? $this->query[$field] ?? $default;
    }

    /**
     * Extracts a specific parameter from the incoming request data.
     *
     * @param string $field
     * @param mixed  $default
     * @return mixed
     */
    public function input(string $field, mixed $default = null): mixed
    {
        if (in_array($this->method, ["GET", "HEAD", "OPTIONS", "TRACE"])) {
            return $this->get($field, $default);
        }

        return $this->body[$field] ?? $this->parameters[$field] ?? $default;
    }

    /**
     * Sanitizes and validates request input using field-specific callables.
     *
     * @param array<string, array<callable>|callable> $rules
     * @return array<string, mixed>|false
     */
    public function sanitize(array $rules): array|false
    {
        $sanitized = [];

        foreach ($rules as $field => $chain) {
            $value = $this->input($field);

            try {
                if (is_array($chain)) {
                    foreach ($chain as $fn) {
                        $value = $fn($value);
                    }
                } else {
                     $value = $chain($value);
                }

                $sanitized[$field] = $value;
            } catch (Throwable $e) {
                $this->errors[$field][] = $e->getMessage();
            }
        }

        return empty($this->errors) ? $sanitized : false;
    }
}

class Response
{
    /** @var int */
    public protected(set) int $status = 200;

    /** @var array<string, mixed> */
    public protected(set) array $headers = [];

    /** @var bool|float|int|string|Stringable|null */
    public protected(set) bool|float|int|string|Stringable|null $body = null;

    /**
     * Returns a new Response instance with the specified HTTP status code.
     *
     * @param int $status
     * @return static
     */
    public function withStatus(int $status): static
    {
        $that = clone $this;
        $that->status = $status;
        return $that;
    }

    /**
     * Returns a new Response instance with additional headers merged into the existing headers.
     *
     * @param array<string, mixed> $headers
     * @return static
     */
    public function addHeaders(array $headers): static
    {
        $that = clone $this;
        $that->headers = array_merge($that->headers, $headers);
        return $that;
    }

    /**
     * Returns a new Response instance with the headers replaced by the provided array.
     *
     * @param array<string, mixed> $headers
     * @return static
     */
    public function withHeaders(array $headers): static
    {
        $that = clone $this;
        $that->headers = $headers;
        return $that;
    }

    /**
     * Returns a new Response instance with the specified body.
     *
     * @param bool|float|int|string|Stringable|null $body
     * @return static
     */
    public function withBody(bool|float|int|string|Stringable|null $body): static
    {
        $that = clone $this;
        $that->body = $body;
        return $that;
    }

    /**
     * Sends the HTTP response to the client.
     *
     * @param bool $detachResponse
     * @return bool
     */
    public function send(bool $detachResponse = false): bool
    {
        if (headers_sent()) {
            return false;
        }

        try {
            http_response_code($this->status);

            foreach ($this->headers as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $i => $v) {
                        header(sprintf("%s: %s", $key, $v), $i === 0);
                    }
                } else {
                    header(sprintf("%s: %s", $key, $value), true);
                }
            }

            echo (string) $this->body;

            if ($detachResponse) {
                session_write_close();
                if (function_exists("fastcgi_finish_request")) {
                    return fastcgi_finish_request();
                } else {
                    flush();
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

class Router
{
    protected const LEAFNODE = "\x00LEAF_NODE";
    protected const WILDCARD = "\x00WILDCARD";

    /** @var list<callable> */
    protected array $globalMiddleware = [];

    /** @var string */
    protected string $prefix = '';

    /** @var list<callable> */
    protected array $middleware = [];

    /** @var array<string, array{list<callable>, callable}> */
    protected array $routes = [];

    /**
     * Add middleware that will be applied globally
     *
     * @param callable $middleware
     * @return static
     */
    public function use(callable $middleware): static
    {
        $this->globalMiddleware[] = $middleware;
        return $this;
    }

    /**
     * Groups routes under a shared prefix and middleware stack for scoped handling.
     *
     * @param string         $prefix
     * @param callable       $handle
     * @param list<callable> $middleware
     * @return static
     */
    public function group(string $prefix, callable $handle, array $middleware = []): static
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->middleware;

        $this->prefix .= $prefix;
        $this->middleware = array_merge($this->middleware, $middleware);

        $handle($this);

        $this->prefix = $previousPrefix;
        $this->middleware = $previousMiddleware;

        return $this;
    }

    /**
     * Registers a route with the router.
     *
     * @param string         $method
     * @param string         $path
     * @param callable       $handle
     * @param list<callable> $middleware
     * @return static
     */
    public function add(string $method, string $path, callable $handle, array $middleware = []): static
    {
        $path = trim((string) preg_replace("/\/+/", "/", $this->prefix . $path), "/");
        $node = &$this->routes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                $node = &$node[static::WILDCARD];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        $middlewares = array_merge($this->globalMiddleware, $this->middleware, $middleware);
        $node[static::LEAFNODE][$method] = [$params, $middlewares, $handle];
        return $this;
    }

    /**
     * Dispatches the incoming HTTP request and executes the corresponding route.
     *
     * @param Request $request
     * @return Response
     * @throws HttpException
     */
    public function dispatch(Request $request): Response
    {
        $result = $this->search($this->routes, explode("/", $request->path));

        if ($result === null) {
            throw HttpException::new(404);
        }

        [$values, $methods] = $result;

        if (!isset($methods[$request->method])) {
            throw HttpException::new(405);
        }

        [$params, $middleware, $handle] = $methods[$request->method];

        $req = $request->setParameters(array_combine($params, $values));
        return $this->call($req, $middleware, $handle);
    }

    /**
     * Recursively searches the route trie for a matching route.
     *
     * @param array $trie
     * @param array $segments
     * @param array $params
     * @return array|null
     */
    protected function search(array $trie, array $segments, array $params = []): ?array
    {
        if (empty($segments)) {
            return isset($trie[static::LEAFNODE]) ? [$params, $trie[static::LEAFNODE]] : null;
        }

        $segment = array_shift($segments);

        if (isset($trie[$segment])) {
            if ($result = $this->search($trie[$segment], $segments, $params)) {
                return $result;
            }
        }

        if (isset($trie[static::WILDCARD])) {
            $params[] = $segment;

            if ($result = $this->search($trie[static::WILDCARD], $segments, $params)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Executes the route handler within a middleware pipeline.
     *
     * @param Request  $request
     * @param array    $middleware
     * @param callable $handle
     * @return Response
     */
    protected function call(Request $request, array $middleware, callable $handle): Response
    {
        $pipeline = $handle;

        foreach (array_reverse($middleware) as $m) {
            $pipeline = fn($req, $res): mixed => call_user_func($m, $req, $res, $pipeline);
        }

        $response = new Response();
        $result = call_user_func($pipeline, $request, $response);

        if ($result instanceof Response) {
            return $result;
        }

        return $response;
    }
}

class Session
{
    protected const FLASH_OLD = "\x00FLASH_OLD";
    protected const FLASH_NEW = "\x00FLASH_NEW";

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[static::FLASH_OLD] = $_SESSION[static::FLASH_NEW] ?? [];
        $_SESSION[static::FLASH_NEW] = [];
    }

    /**
     * Stores a value in the session under the specified key.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieves a value from the session by key.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Stores a temporary flash value in the session under the specified key.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function flash(string $key, mixed $value): void
    {
        $_SESSION[static::FLASH_NEW][$key] = $value;
    }

    /**
     * Retrieves a value from the flash (old) session by key.
     *
     * @param string $key
     * @return mixed
     */
    public function restore(string $key): mixed
    {
        return $_SESSION[static::FLASH_OLD][$key] ?? null;
    }
}

/**
 * If no identifier is provided, returns the container instance.
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return ($id is class-string<T> ? T : object)
 */
function app(?string $id = null, array $dependancies = []): object
{
    return is_null($id) ? Application::$container : Application::$container->resolve($id, $dependancies);
}

/**
 * Attempt to resolve path from base as passed to Application factory.
 *
 * @param string $path
 * @return string
 */
function base(string $path = ''): string
{
    return Application::fromBase($path);
}

/**
 * This function fetches an environment variable from the Environment instance.
 *
 * @param ?string $key
 * @param mixed $default
 * @return mixed
 */
function env(?string $key = null, mixed $default = null): mixed
{
    return is_null($key) ? app(Environment::class) : app(Environment::class)->get($key, $default);
}

/**
 * This function binds a service to the container using the specified identifier and factory.
 *
 * @param string $abstract
 * @param Closure|string|null $concrete
 * @return void
 */
function bind(string $abstract, Closure|string|null $concrete): void
{
    app()->bind($abstract, $concrete);
}

function once(string $abstract, Closure|string|null $concrete): void
{
    app()->once($abstract, $concrete);
}

/**
 * This function retrieves a command-line argument using the specified key.
 *
 * @param int|string|null $key
 * @param mixed           $default
 * @return mixed
 */
function arg(int|string|null $key = null, mixed $default = null): mixed
{
    return is_null($key) ? app(Argument::class) : app(Argument::class)->get($key, $default);
}

/**
 * Executes the provided command handler if the current command matches the specified name.
 *
 * @param string   $name
 * @param callable $handle
 * @return void
 */
function command(string $name, callable $handle): void
{
    if (!Application::isCli()) {
        return;
    }

    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    $result = $handle($argument);

    exit(is_int($result) ? $result : 0);
}

/**
 * Fetches a value from the current Request instance using the specified key.
 *
 * @param ?string $field
 * @param mixed   $default
 * @return mixed
 */
function request(?string $field = null, mixed $default = null): mixed
{
    return is_null($field) ? app(Request::class) : app(Request::class)->get($field, $default);
}

/**
 * Fetches a value from the current Request instance body using the specified key.
 *
 * @param string $field
 * @param mixed  $default
 * @return mixed
 */
function input(string $field, mixed $default = null): mixed
{
    return app(Request::class)->input($field, $default);
}

/**
 * Sanitizes and validates request input using field-specific callables.
 *
 * @param array<string, array<Closure>> $rules
 * @param ?Closure $failed
 * @return array<string, mixed>|false
 */
function sanitize(array $rules, ?Closure $failed = null): array|false
{
    $data = app(Request::class)->sanitize($rules);

    if ($failed !== null && $data === false) {
        $failed(app(Request::class)->errors);
    }

    return $data;
}

/**
 * Add middleware that will be applied globally.
 *
 * @param callable $middleware
 * @return void
 */
function middleware(callable $middleware): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->use($middleware);
}

/**
 * Groups routes under a shared prefix and middleware stack for scoped handling.
 *
 * @param string   $prefix
 * @param callable $handle
 * @param array    $middleware
 * @return void
 */
function group(string $prefix, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->group($prefix, $handle, $middleware);
}

/**
 * Create a GET method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function get(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("GET", $path, $handle, $middleware);
}

/**
 * Create a POST method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function post(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("POST", $path, $handle, $middleware);
}

/**
 * Create a PUT method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function put(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("PUT", $path, $handle, $middleware);
}

/**
 * Create a PATCH method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function patch(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("PATCH", $path, $handle, $middleware);
}

/**
 * Create a DELETE method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function delete(string $path, callable $handle, array $middleware = []): void
{
    if (Application::isCli()) {
        return;
    }

    app(Router::class)->add("DELETE", $path, $handle, $middleware);
}

/**
 * Sets flash data if a value is provided, or retrieves and removes flash data for the given key.
 *
 * @param string $key
 * @param mixed  $value
 * @return mixed
 */
function flash(string $key, mixed $value = null): mixed
{
    if (!Application::isWeb()) {
        return null;
    }

    if (func_num_args() === 1) {
        return app(Session::class)->restore($key);
    }

    app(Session::class)->flash($key, $value);
    return $value;
}

/**
 * Sets session data if a value is provided, or retrieves session data for the given key.
 *
 * @param string $key
 * @param mixed  $value
 * @return mixed
 */
function session(string $key, mixed $value = null): mixed
{
    if (!Application::isWeb()) {
        return null;
    }

    if (func_num_args() === 1) {
        return app(Session::class)->get($key);
    }

    app(Session::class)->set($key, $value);
    return $value;
}

/**
 * Retrieves the CSRF token from the session or generates a new one if absent.
 *
 * @return string
 */
function csrf(): ?string
{
    if (!Application::isWeb()) {
        return null;
    }

    if ($token = session('\0SESSION_CSRF')) {
        return $token;
    }

    return session('\0SESSION_CSRF', bin2hex(random_bytes(32)));
}

/**
 * Validates the provided CSRF token against the session token and rotates if valid.
 *
 * @param string $csrf
 * @return bool
 */
function csrf_verify(string $csrf): ?bool
{
    if (!Application::isWeb()) {
        return null;
    }

    if ($valid = hash_equals(session('\0SESSION_CSRF'), $csrf)) {
        session('\0SESSION_CSRF', bin2hex(random_bytes(32)));
    }

    return $valid;
}

function jwt(array $payload): ?string
{
    if (!Application::isApi()) {
        return null;
    }

    return app(Jwt::class)->encode($payload);
}

function jwt_decode(string $token): ?array
{
    if (!Application::isApi()) {
        return null;
    }

    return app(Jwt::class)->decode($token);
}

/**
 * Renders a template with the provided data.
 *
 * @param string $template
 * @param array  $data
 * @return string
 */
function render(string $template, array $data = []): string
{
    $class = Template::class;

    if (class_exists($class)) {
        return new $class($template)->render($data);
    }

    return vsprintf($template, $data);
}

/**
 * Returns a Response instance configured to redirect to the specified URI with the given status code.
 *
 * @param string $uri
 * @param int    $status
 * @return Response
 */
function redirect(string $uri, int $status = 302): Response
{
    return app(Response::class)->withStatus($status)->withHeaders(["Location" => $uri]);
}

/**
 * Returns a Response instance configured to send HTML content with the specified status code.
 *
 * @param string $html
 * @param int    $status
 * @return Response
 */
function html(string $html, int $status = 200): Response
{
    return app(Response::class)
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "text/html"])
        ->withBody($html);
}

/**
 * Returns a Response instance configured to send JSON data with the specified status code.
 *
 * @param mixed $data
 * @param int   $status
 * @return Response
 */
function json(mixed $data, int $status = 200): Response
{
    return app(Response::class)
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "application/json"])
        ->withBody(json_encode($data));
}

/**
 * Returns a Response instance configured to send plain text with the specified status code.
 *
 * @param string $text
 * @param int    $status
 * @return Response
 */
function text(string $text, int $status = 200): Response
{
    return app(Response::class)
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "text/plain"])
        ->withBody($text);
}

/**
 * Returns a Response instance configured to render an HTML view using the provided template and data.
 *
 * @param string $template
 * @param array  $data
 * @param int    $status
 * @return Response
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}

/**
 * In CLI mode, the data is dumped using var_dump. In a web environment, the output is wrapped in <pre> tags.
 *
 * @param mixed ...$data
 * @return void
 */
function dump(...$data): void
{
    if (Application::isCli()) {
        var_dump(...$data);
    } elseif (Application::isApi()) {
        echo json_encode($data);
    } else {
        echo "<pre>";
        var_dump(...$data);
        echo "</pre>";
    }

    die();
}
