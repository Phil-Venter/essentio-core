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

class Cast
{
    /**
     * Returns a closure that casts input to boolean, or throws an error on failure.
     *
     * @param string $message Error message for invalid boolean.
     * @return Closure(string): ?bool
     */
    public function bool(string $message = ''): Closure
    {
        return function (string $input) use ($message): ?bool {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $bool = filter_var($input, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            if ($bool === null) {
                throw new Exception($message);
            }

            return $bool;
        };
    }

    /**
     * Returns a closure that casts input to DateTimeImmutable, or throws on failure.
     *
     * @param string $message Error message for invalid date.
     * @return Closure(string): ?DateTimeInterface
     */
    public function date(string $message = ''): Closure
    {
        return function (string $input) use ($message): ?DateTimeInterface {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            try {
                return new DateTimeImmutable($input);
            } catch (Exception) {
                throw new Exception($message);
            }
        };
    }

    /**
     * Returns a closure that casts input to an enum case using tryFrom().
     *
     * @param class-string<BackedEnum> $enumClass Enum class to resolve.
     * @param string                    $message   Error message if resolution fails.
     * @return Closure(string): ?BackedEnum
     * @throws Exception If class is not a backed enum.
     */
    public function enum(string $enumClass, string $message = ''): Closure
    {
        if (!enum_exists($enumClass)) {
            throw new Exception("Invalid enum class: $enumClass");
        }

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new Exception("Enum must be a backed enum");
        }

        return function (string $input) use ($enumClass, $message): ?BackedEnum {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $enum = $enumClass::tryFrom($input);

            if ($enum === null) {
                throw new Exception($message);
            }

            return $enum;
        };
    }

    /**
     * Returns a closure that casts input to float.
     *
     * @param string $message Error message if cast fails.
     * @return Closure(string): ?float
     */
    public function float(string $message = ''): Closure
    {
        return function (string $input) use ($message): ?float {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = $this->normalizeNumber($input, $message);
            $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($floatVal === false) {
                throw new Exception($message);
            }

            return $floatVal;
        };
    }

    /**
     * Returns a closure that casts input to int.
     *
     * @param string $message Error message if cast fails.
     * @return Closure(string): ?int
     */
    public function int(string $message = ''): Closure
    {
        return function (string $input) use ($message): ?int {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = $this->normalizeNumber($input, $message);
            $intVal = filter_var($value, FILTER_VALIDATE_INT);

            if ($intVal === false) {
                throw new Exception($message);
            }

            return $intVal;
        };
    }

    /**
     * Returns a closure that casts input to either int or float.
     *
     * @param string $message Error message if cast fails.
     * @return Closure(string): int|float|null
     */
    public function numeric(string $message = ''): Closure
    {
        return function (string $input) use ($message): int|float|null {
            $input = $this->nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = $this->normalizeNumber($input, $message);
            $intVal = filter_var($value, FILTER_VALIDATE_INT);

            if ($intVal === false) {
                return $intVal;
            }

            $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($floatVal === false) {
                return $floatVal;
            }

            throw new Exception($message);
        };
    }

    /**
     * Returns a closure that optionally trims input and returns it as a string.
     *
     * @param bool $trim If true, trims whitespace.
     * @return Closure(string): string
     */
    public function string(bool $trim = false): Closure
    {
        return function (string $input) use ($trim): string {
            if ($trim) {
                return trim($input);
            }

            return $input;
        };
    }

    /**
     * Returns null for empty strings (after trimming), otherwise returns the string.
     *
     * @param string $input Input string.
     * @return mixed|null Original string or null.
     * @internal
     */
    protected function nullOnEmpty(string $input): mixed
    {
        if (trim($input) === "") {
            return null;
        }

        return $input;
    }

    /**
     * Extracts and returns the first numeric pattern from input string.
     *
     * @param string $input Raw input string.
     * @param string $message Error to throw if no valid number found.
     * @return string Extracted numeric string.
     * @throws Exception If no valid number pattern is found.
     * @internal
     */
    protected function normalizeNumber(string $input, string $message): string
    {
        preg_match_all("/-?\d+(\.\d+)?/", $input, $matches);

        if (empty($matches[0])) {
            throw new Exception($message);
        }

        return $matches[0][0];
    }
}

class Mailer
{
    /** @var string */
    protected string $url;

    /** @var string */
    public protected(set) string $from = '';

    /** @var list<string> */
    public protected(set) array $to = [];

    /** @var string */
    public protected(set) string $subject = '';

    /** @var string */
    public protected(set) string $text = '';

    /** @var string */
    public protected(set) string $html = '';

    /**
     * Initializes a new Mailer instance.
     *
     * @param string $url  SMTP server hostname.
     * @param string $user SMTP username.
     * @param string $pass SMTP password.
     * @param int    $port SMTP port (default 587).
     */
    public function __construct(
        string $url,
        protected string $user,
        protected string $pass,
        int $port = 587,
    ) {
        $this->url = sprintf("smtp://%s:%s", $url, $port);
    }

    /**
     * Sets the sender address.
     *
     * @param string $email Sender address.
     * @return static
     */
    public function withFrom(string $email): static
    {
        $that = clone $this;
        $that->from = $email;
        return $that;
    }

    /**
     * Adds a recipient address.
     *
     * @param string $email Recipient address.
     * @return static
     */
    public function addTo(string $email): static
    {
        $that = clone $this;
        $that->to[] = $email;
        return $that;
    }

    /**
     * Replaces recipient list with one or more addresses.
     *
     * @param list<string>|string $emails One or more recipient addresses.
     * @return static
     */
    public function withTo(array|string $emails): static
    {
        $that = clone $this;
        $that->to = (array) $emails;
        return $that;
    }

    /**
     * Sets the message subject.
     *
     * @param string $subject Message subject line.
     * @return static
     */
    public function withSubject(string $subject): static
    {
        $that = clone $this;
        $that->subject = $subject;
        return $that;
    }

    /**
     * Sets the plaintext body.
     *
     * @param string $text Plaintext content.
     * @return static
     */
    public function withText(string $text): static
    {
        $that = clone $this;
        $that->text = $text;
        return $that;
    }

    /**
     * Sets the HTML body.
     *
     * @param string $html HTML content.
     * @return static
     */
    public function withHtml(string $html): static
    {
        $that = clone $this;
        $that->html = $html;
        return $that;
    }

    /**
     * Sends the composed email via SMTP.
     *
     * @return true
     * @throws RuntimeException On transport or cURL error.
     */
    public function send(): true
    {
        assert(!empty($this->from));
        assert(!empty($this->to));
        assert(!empty($this->subject));

        $stream = fopen("php://temp", "r+");
        if (!$stream) {
            throw new RuntimeException("Failed to open in-memory stream.");
        }

        fwrite($stream, $this->buildEmail());
        rewind($stream);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
            CURLOPT_MAIL_FROM => sprintf("<%s>", $this->from),
            CURLOPT_MAIL_RCPT => array_map(fn($to): string => sprintf("<%s>", $to), $this->to),
            CURLOPT_USERNAME => $this->user,
            CURLOPT_PASSWORD => $this->pass,
            CURLOPT_USE_SSL => CURLUSESSL_ALL,
            CURLOPT_READFUNCTION => fn ($ch, $stream, $length): string|false => fread($stream, $length),
            CURLOPT_INFILE => $stream,
            CURLOPT_UPLOAD => true,
            CURLOPT_VERBOSE => true,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        curl_close($ch);
        fclose($stream);

        if ($errno !== 0 || $result === false) {
            throw new RuntimeException(sprintf("cURL error (%s): %s", $errno, $error));
        }

        if ($httpCode >= 400) {
            throw new RuntimeException(sprintf("Email send failed with HTTP status code: %s", $httpCode));
        }

        return true;
    }

    /**
     * Composes the MIME-formatted email message body and headers.
     *
     * @return string Full email content including headers.
     * @internal
     */
    protected function buildEmail(): string
    {
        $headers = [
            sprintf("From: %s", $this->from),
            sprintf("To: %s", implode(", ", $this->to)),
            sprintf("Date: %s", date("r")),
            sprintf("Subject: %s", $this->subject),
            "MIME-Version: 1.0",
        ];

        if (!empty($this->text) && !empty($this->html)) {
            $boundary = uniqid("np");
            $headers[] = sprintf("Content-Type: multipart/alternative; boundary=%s", $boundary);
            $body = sprintf(<<<'EOT'
                --%s\r
                Content-Type: text/plain; charset=utf-8\r
                \r
                %s\r
                \r
                --%s\r
                Content-Type: text/html; charset=utf-8\r
                \r
                %s\r
                \r
                --%s--\r
                EOT,
                $boundary, $this->text, $boundary, $this->html, $boundary
            );
        } elseif (!empty($this->html)) {
            $headers[] = "Content-Type: text/html; charset=utf-8";
            $body = $this->html;
        } else {
            $headers[] = "Content-Type: text/plain; charset=utf-8";
            $body = $this->text;
        }

        return sprintf("%s\r\n\r\n%s", implode("\r\n", $headers), $body);
    }
}

class Query
{
    /** @var list<string> */
    protected array $columns = [];

    /** @var bool */
    protected bool $subquery = false;

    /** @var string */
    protected string $from = '';

    /** @var list<string> */
    protected array $joins = [];

    /** @var list<string> */
    protected array $unions = [];

    /** @var list<string> */
    protected array $wheres = [];

    /** @var list<string> */
    protected array $group = [];

    /** @var list<string> */
    protected array $havings = [];

    /** @var list<string> */
    protected array $orderBy = [];

    /** @var ?int */
    protected ?int $limit = null;

    /** @var ?int */
    protected ?int $offset = null;

    /** @var list<mixed> */
    protected array $whereBindings = [];

    /** @var list<mixed> */
    protected array $havingBindings = [];

    /** @var list<mixed> */
    protected array $unionBindings = [];

    public function __construct(
        protected PDO $pdo,
    ) {
    }

    /**
     * @return PDO
     */
    public function getPDO(): PDO
    {
        return $this->pdo;
    }

    /**
     * Specifies columns to select.
     *
     * @param string ...$columns Column names.
     * @return static
     */
    public function select(string ...$columns): static
    {
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    /**
     * Specifies the table to select from. Can accept a closure to build a subquery.
     *
     * @param Closure|string $table Table name or closure for subquery.
     * @return static
     */
    public function from(Closure|string $table): static
    {
        assert(empty($this->from));

        if ($table instanceof Closure) {
            return $this->subquery($table);
        }

        $this->from = $table;
        return $this;
    }

    /**
     * Defines a subquery as the source table.
     *
     * @param Closure     $table Callback that builds the subquery.
     * @param string|null $as Optional alias.
     * @return static
     */
    public function subquery(Closure $table, ?string $as = null): static
    {
        assert(empty($this->from));

        $query = new static($this->pdo);
        $table($query);

        $this->subquery = true;
        $this->from = sprintf("(%s) AS %s", $query->compileSelect(), $as ?? "t");
        $this->whereBindings = array_merge($this->whereBindings, $query->getBindings());

        return $this;
    }

    /**
     * Adds a JOIN clause to the query.
     *
     * @param string      $table Join table.
     * @param string|null $first Left column or 'using'.
     * @param string|null $op Operator or column (if using 'using').
     * @param string|null $second Right column (optional).
     * @param string      $type Type of join (INNER, LEFT, etc.).
     * @return static
     */
    public function join(
        string $table,
        ?string $first = null,
        ?string $op = null,
        ?string $second = null,
        string $type = '',
    ): static
    {
        if (in_array(strtolower($type), ["cross", "natural"])) {
            $this->joins[] = sprintf("%s JOIN %s", $type, $table);
            return $this;
        }

        if ($op !== null && strtolower($first ?? "") === "using") {
            $this->joins[] = sprintf("%s JOIN %s USING(%s)", $type, $table, $op);
            return $this;
        }

        if ($op !== null && $second === null) {
            $second = $op;
            $op = null;
        }

        if ($first === null || $second === null) {
            [$mainTable, $mainAlias] = $this->extractAlias($this->from);
            [$joinTable, $joinAlias] = $this->extractAlias($table);

            $first ??= sprintf("%s.id", $mainAlias ?? $mainTable);
            $second ??= sprintf("%s.%s_id", $joinAlias ?? $joinTable, $mainTable);
        }

        $op ??= "=";

        $this->joins[] = sprintf("%s JOIN %s ON %s %s %s", $type, $table, $first, $op, $second);
        return $this;
    }

    /**
     * Adds a UNION or UNION ALL clause to the query.
     *
     * @param Closure $callback Callback that builds the union subquery.
     * @param string  $type Additional UNION keyword like ALL.
     * @return static
     */
    public function union(Closure $callback, string $type = ''): static
    {
        $query = new static($this->pdo);
        $callback($query);

        $this->unions[] = sprintf("UNION %s %s", $type, $query->compileSelect());
        $this->unionBindings = array_merge($this->unionBindings, $query->getBindings());

        return $this;
    }

    /**
     * Adds a WHERE condition to the query.
     *
     * @param string|Closure $column Column name or closure for grouped conditions.
     * @param string|null    $op Operator (optional).
     * @param mixed          $value Value to compare against (optional).
     * @param string         $type Logical operator ("AND" or "OR").
     * @return static
     */
    public function where(string|Closure $column, ?string $op = null, mixed $value = null, string $type = 'AND'): static
    {
        [$sql, $bindings] = $this->makeCondition($column, $op, $value, $type, "where");
        $this->wheres[] = $sql;
        $this->whereBindings = array_merge($this->whereBindings, $bindings);
        return $this;
    }

    /**
     * Adds an OR WHERE condition.
     *
     * @param string|Closure $column Column name or closure.
     * @param string|null    $op Operator (optional).
     * @param mixed          $value Value (optional).
     * @return static
     */
    public function orWhere(string|Closure $column, ?string $op = null, mixed $value = null): static
    {
        return $this->where($column, $op, $value, "OR");
    }

    /**
     * Adds GROUP BY clauses.
     *
     * @param string ...$columns Column names.
     * @return static
     */
    public function group(string ...$columns): static
    {
        $this->group = array_merge($this->group, $columns);
        return $this;
    }

    /**
     * Adds a HAVING condition.
     *
     * @param string|Closure $column Column name or closure for grouped conditions.
     * @param string|null    $op Operator.
     * @param mixed          $value Value.
     * @param string         $type Logical operator.
     * @return static
     */
    public function having(
        string|Closure $column,
        ?string $op = null,
        mixed $value = null,
        string $type = 'AND',
    ): static
    {
        [$sql, $bindings] = $this->makeCondition($column, $op, $value, $type, "having");
        $this->havings[] = $sql;
        $this->havingBindings = array_merge($this->havingBindings, $bindings);
        return $this;
    }

    /**
     * Adds an OR HAVING condition.
     *
     * @param string|Closure $column Column name or closure.
     * @param string|null    $op Operator.
     * @param mixed          $value Value.
     * @return static
     */
    public function orHaving(string|Closure $column, ?string $op = null, mixed $value = null): static
    {
        return $this->having($column, $op, $value, "OR");
    }

    /**
     * Adds ORDER BY clause.
     *
     * @param string $column Column name.
     * @param string $direction Sort direction ("ASC" or "DESC").
     * @return static
     */
    public function order(string $column, string $direction = 'ASC'): static
    {
        $this->orderBy[] = sprintf("%s %s", $column, $direction);
        return $this;
    }

    /**
     * Adds LIMIT and OFFSET clauses.
     *
     * @param int      $limit Max number of rows.
     * @param int|null $offset Number of rows to skip.
     * @return static
     */
    public function limit(int $limit, ?int $offset = null): static
    {
        $this->limit = $limit;
        $this->offset = $offset;

        return $this;
    }

    /**
     * Executes a SELECT query and returns all rows.
     *
     * @return iterable
     */
    public function get(): iterable
    {
        $sql = $this->compileSelect();

        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $this->getBindings());
        $stmt->execute();

        return $stmt->getIterator();
    }

    /**
     * Lazily yields transformed rows from the SELECT result.
     *
     * @param callable $fn Transformation function, called for each row.
     *                     If callable expects multiple arguments, they are passed from the row by key.
     *                     Ensure keys in SELECT match parameter names.
     * @return iterable<mixed> Generator yielding transformed results.
     */
    public function morph(callable $fn, bool $spread = false): iterable
    {
        foreach ($this->get() as $row) {
            $params = iterator_to_array($row);
            yield $spread ? $fn(...$params) : $fn($params);
        }
    }

    /**
     * Executes a SELECT query and returns the first row.
     *
     * @return array|null Single result row or null.
     */
    public function first(): array
    {
        $this->limit = 1;
        $rows = iterator_to_array($this->get());
        return $rows[0] ?? [];
    }

    /**
     * Executes an INSERT query.
     *
     * @param array $data Key-value pairs of column => value.
     * @return int|null Last inserted ID or null if none.
     */
    public function insert(array $data): ?int
    {
        assert(!empty($data));
        assert(!$this->subquery);
        assert(!empty($this->from));

        $columns = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $this->from, $columns, $placeholders);

        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $data);
        $stmt->execute();

        return $this->pdo->lastInsertId() ? (int) $this->pdo->lastInsertId() : null;
    }

    /**
     * Executes an UPDATE query.
     *
     * @param array $data Key-value pairs of column => value.
     * @return int Number of affected rows.
     */
    public function update(array $data): int
    {
        assert(!empty($data));
        assert(!$this->subquery);
        assert(!empty($this->from));

        $setParts = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $setParts[] = "$column = ?";
            $bindings[] = $value;
        }

        $sql = sprintf("UPDATE %s SET %s", $this->from, implode(", ", $setParts));
        if ($where = $this->compileWhere()) {
            $sql .= sprintf(" WHERE %s", $where);
        }

        $bindings = array_merge($bindings, $this->getBindings());

        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $bindings);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Executes a DELETE query.
     *
     * @return int Number of affected rows.
     */
    public function delete(): int
    {
        assert(!$this->subquery);
        assert(!empty($this->from));

        $sql = sprintf("DELETE FROM %s", $this->from);
        if ($where = $this->compileWhere()) {
            $sql .= sprintf(" WHERE %s", $where);
        }

        $stmt = $this->pdo->prepare($sql);
        $this->bindValues($stmt, $this->getBindings());
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * Compiles the SQL SELECT statement as a string.
     *
     * @return string SQL query.
     * @internal
     */
    public function compileSelect(): string
    {
        assert(!empty($this->from));

        $sql = sprintf("SELECT %s FROM %s", implode(", ", $this->columns ?: ["*"]), $this->from);

        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }

        if ($where = $this->compileWhere()) {
            $sql .= sprintf(" WHERE %s", $where);
        }

        if (!empty($this->group)) {
            $sql .= sprintf(" GROUP BY %s", implode(", ", $this->group));

            if ($having = $this->compileHaving()) {
                $sql .= sprintf(" HAVING %s", $having);
            }
        }

        if (!empty($this->orderBy)) {
            $sql .= sprintf(" ORDER BY %s", implode(", ", $this->orderBy));
        }

        if ($this->limit !== null) {
            $sql .= sprintf(" LIMIT %s", $this->limit);
            if ($this->offset !== null) {
                $sql .= sprintf(" OFFSET %s", $this->offset);
            }
        }

        if (!empty($this->unions)) {
            $sql .= " " . implode(" ", $this->unions);
        }

        return $sql;
    }

    /**
     * Compiles the WHERE clause.
     *
     * @return string SQL WHERE clause (no "WHERE" keyword).
     * @internal
     */
    public function compileWhere(): string
    {
        if (empty($this->wheres)) {
            return "";
        }

        return $this->stripLeadingBoolean(implode(" ", $this->wheres));
    }

    /**
     * Compiles the HAVING clause.
     *
     * @return string SQL HAVING clause (no "HAVING" keyword).
     * @internal
     */
    public function compileHaving(): string
    {
        if (empty($this->havings)) {
            return "";
        }

        return $this->stripLeadingBoolean(implode(" ", $this->havings));
    }

    /**
     * Returns all bound parameters across where, having, and unions.
     *
     * @return array All query bindings.
     * @internal
     */
    public function getBindings(): array
    {
        return array_merge($this->whereBindings, $this->havingBindings, $this->unionBindings);
    }

    /**
     * Parses a table string and returns base name and alias (if present).
     *
     * @param string $str Table declaration.
     * @return array Array with [table, alias|null].
     * @internal
     */
    protected function extractAlias(string $str): array
    {
        $str = trim($str);
        $parts = explode(" ", $str);

        if (count($parts) === 3 && strtolower($parts[1]) === "as") {
            return [$parts[0], $parts[2]];
        }

        if (count($parts) === 2) {
            return $parts;
        }

        return [$str, null];
    }

    /**
     * Builds a condition string and binding values.
     *
     * @param string|Closure $column Column or nested condition.
     * @param string|null    $op Operator.
     * @param mixed          $value Value to bind.
     * @param string         $type Logical operator.
     * @param string         $clause Clause type ("where" or "having").
     * @return array [string SQL, array bindings]
     * @internal
     */
    protected function makeCondition(
        string|Closure $column,
        ?string $op = null,
        mixed $value = null,
        string $type = 'AND',
        string $clause = 'where',
    ): array
    {
        if ($column instanceof Closure) {
            $query = new static($this->pdo);
            $column($query);
            $sql = $clause === "where" ? $query->compileWhere() : $query->compileHaving();

            if (empty(trim($sql))) {
                return [sprintf("%s (%s)", $type, "1=1"), []];
            }

            return [sprintf("%s (%s)", $type, $sql), $query->getBindings()];
        }

        if (is_string($op) && str_contains(strtolower($op), "null")) {
            return [sprintf("%s %s %s", $type, $column, $op), []];
        }

        if ($value instanceof Closure) {
            $query = new static($this->pdo);
            $value($query);
            return [
                sprintf("%s %s %s (%s)", $type, $column, $op ?? "IN", $query->compileSelect()),
                $query->getBindings(),
            ];
        }

        if (is_array($value)) {
            $placeholders = implode(", ", array_fill(0, count($value), "?"));
            return [sprintf("%s %s %s (%s)", $type, $column, $op ?? "IN", $placeholders), $value];
        }

        if ($value === null && $op !== null) {
            $value = $op;
            $op = "=";
        }

        return [sprintf("%s %s %s ?", $type, $column, $op ?? "="), [$value]];
    }

    /**
     * Binds values to a PDOStatement with appropriate types.
     *
     * @param PDOStatement $stmt Prepared statement.
     * @param array        $bindings Values to bind.
     * @return PDOStatement Bound statement.
     * @internal
     */
    protected function bindValues(PDOStatement $stmt, array $bindings): void
    {
        foreach (array_values($bindings) as $index => $value) {
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };

            $stmt->bindValue($index + 1, $value, $type);
        }
    }

    /**
     * Removes leading AND/OR from condition strings.
     *
     * @param string $clause SQL clause.
     * @return string Cleaned clause.
     * @internal
     */
    protected function stripLeadingBoolean(string $clause): string
    {
        $trimmed = ltrim($clause);
        if (stripos($trimmed, "AND ") === 0) {
            return substr($trimmed, 4); // length of 'AND '
        }

        if (stripos($trimmed, "OR ") === 0) {
            return substr($trimmed, 3); // length of 'OR '
        }

        return $trimmed;
    }
}

class Template
{
    /** @var ?self */
    protected ?self $layout = null;

    /** @var list<string> */
    protected array $stack = [];

    /** @var array<string,string> */
    protected array $segments = [];

    /**
     * @param mixed $path
     */
    public function __construct(
        protected ?string $path = null,
    ) {
    }

    /**
     * Sets the layout template to be used for rendering.
     *
     * @param string $path
     * @return void
     */
    protected function layout(string $path): void
    {
        $this->layout = new Template($path);
    }

    /**
     * Retrieves the content of a named segment or returns a default string.
     *
     * @param string $name
     * @param string $default
     * @return string
     */
    protected function yield(string $name, string $default = ''): string
    {
        return $this->segments[$name] ?? $default;
    }

    /**
     * Starts or sets a named content segment.
     *
     * @param string      $name
     * @param string|null $value
     * @return void
     */
    protected function segment(string $name, ?string $value = null): void
    {
        if ($value !== null) {
            $this->segments[$name] = $value;
        } else {
            $this->stack[] = $name;
            ob_start();
        }
    }

    /**
     * Ends the current output buffer and assigns it to the last opened segment.
     *
     * @return void
     */
    protected function end(): void
    {
        assert(!empty($this->stack));

        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    /**
     * Renders the template and returns the resulting HTML string.
     *
     * @param array<string,mixed> $data
     * @return string
     */
    public function render(array $data = []): string
    {
        assert($this->path);
        assert(file_exists($this->path));

        $content = (function (array $data) {
            ob_start();
            extract($data);
            include $this->path;
            return ob_get_clean();
        })($data);

        if ($this->layout !== null) {
            $this->segments["content"] = $content;
            $this->layout->setSegments($this->segments);
            return $this->layout->render($data);
        }

        return $content;
    }

    /**
     * Sets the segment content to be used when rendering the layout.
     *
     * @param array<string,string> $segments
     * @return void
     * @internal
     */
    protected function setSegments(array $segments): void
    {
        $this->segments = $segments;
    }
}

class Validate
{
    /**
     * Allows alphabetic characters only.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function alpha(string $message = ''): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match('/^[a-zA-Z]+$/', $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Allows alphanumeric characters, underscores, and dashes.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function alphaDash(string $message = ''): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match('/^[\w-]+$/', $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Allows letters and numbers only.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function alphaNum(string $message = ''): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match('/^[a-zA-Z0-9]+$/', $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Validates email address format.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function email(string $message = ''): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Ensures input ends with one of the given suffixes.
     *
     * @param array $suffixes List of valid suffixes.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function endsWith(array $suffixes, string $message = ''): Closure
    {
        return function (?string $input) use ($suffixes, $message): ?string {
            if ($input === null) {
                return null;
            }

            foreach ($suffixes as $suffix) {
                if (str_ends_with($input, $suffix)) {
                    return $input;
                }
            }

            throw new Exception($message);
        };
    }

    /**
     * Validates input is entirely lowercase.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function lowercase(string $message = ''): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtolower($input, "UTF-8") !== $input) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Validates input is entirely uppercase.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function uppercase(string $message = ''): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtoupper($input, "UTF-8") !== $input) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Enforces minimum character length.
     *
     * @param int $min Minimum allowed length.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function minLength(int $min, string $message = ''): Closure
    {
        return function (?string $input) use ($min, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) < $min) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Enforces maximum character length.
     *
     * @param int $max Maximum allowed length.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function maxLength(int $max, string $message = ''): Closure
    {
        return function (?string $input) use ($max, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) > $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Validates input against a regular expression pattern.
     *
     * @param string $pattern PCRE pattern to validate input.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function regex(string $pattern, string $message = ''): Closure
    {
        return function (?string $input) use ($pattern, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (!preg_match($pattern, $input)) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Ensures value is between two inclusive bounds.
     *
     * @param DateTimeInterface|float|int $min Lower bound.
     * @param DateTimeInterface|float|int $max Upper bound.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function between(
        DateTimeInterface|float|int $min,
        DateTimeInterface|float|int $max,
        string $message = '',
    ): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use (
            $min,
            $max,
            $message
        ): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value < $min || $value > $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Ensures value is greater than given threshold.
     *
     * @param DateTimeInterface|float|int $min Minimum threshold (exclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function gt(DateTimeInterface|float|int $min, string $message = ''): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($min, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value <= $min) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Ensures value is greater than or equal to threshold.
     *
     * @param DateTimeInterface|float|int $min Minimum threshold (inclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function gte(DateTimeInterface|float|int $min, string $message = ''): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($min, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value < $min) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Ensures value is less than given threshold.
     *
     * @param DateTimeInterface|float|int $max Maximum threshold (exclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function lt(DateTimeInterface|float|int $max, string $message = ''): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($max, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value >= $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Ensures value is less than or equal to threshold.
     *
     * @param DateTimeInterface|float|int $max Maximum threshold (inclusive).
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function lte(DateTimeInterface|float|int $max, string $message = ''): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (
            DateTimeInterface|float|int|null $input
        ) use ($max, $message): DateTimeInterface|float|int|null {
            if ($input === null) {
                return null;
            }

            $value = $input instanceof DateTimeInterface ? $input->getTimestamp() : $input;

            if ($value > $max) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Ensures a value is not null or empty.
     *
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function required(string $message = ''): Closure
    {
        return function (mixed $input) use ($message): mixed {
            if (!isset($input) || (is_string($input) && trim($input) === "")) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Validates membership in a given array.
     *
     * @param array $allowed Valid values.
     * @param bool $strict Use strict type comparison.
     * @param string $message Error message to throw if validation fails.
     * @return Closure
     */
    public function inArray(array $allowed, bool $strict = true, string $message = ''): Closure
    {
        return function (mixed $input) use ($allowed, $strict, $message): mixed {
            if ($input === null) {
                return null;
            }

            if (!in_array($input, $allowed, $strict)) {
                throw new Exception($message);
            }

            return $input;
        };
    }
}

/**
 * If no identifier is provided, returns the container instance.
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return ($id is class-string<T> ? T : object)
 */
function app(string $id = '', array $dependancies = []): object
{
    return func_num_args() === 0
        ? Application::$container
        : Application::$container->resolve($id, $dependancies);
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
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env(string $key = '', mixed $default = null): mixed
{
    return func_num_args() === 0
        ? app(Environment::class)
        : app(Environment::class)->get($key, $default);
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

/**
 * Binds a singleton service to the container.
 *
 * @param string $abstract
 * @param Closure|string|null $concrete
 * @return void
 */
function once(string $abstract, Closure|string|null $concrete): void
{
    app()->once($abstract, $concrete);
}

/**
 * This function retrieves a command-line argument using the specified key.
 *
 * @param int|string $key
 * @param mixed $default
 * @return mixed
 */
function arg(int|string $key = '', mixed $default = null): mixed
{
    return func_num_args() === 0
        ? app(Argument::class)
        : app(Argument::class)->get($key, $default);
}

/**
 * Executes the provided command handler if the current command matches the specified name.
 *
 * @param string $name
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
 * @param string $field
 * @param mixed $default
 * @return mixed
 */
function request(string $field = '', mixed $default = null): mixed
{
    return func_num_args() === 0
        ? app(Request::class)
        : app(Request::class)->get($field, $default);
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

/**
 * Encodes a payload into a JWT string using the configured secret key.
 *
 * @param array $payload
 * @return string|null
 */
function jwt(array $payload): ?string
{
    if (!Application::isApi()) {
        return null;
    }

    return app(Jwt::class)->encode($payload);
}

/**
 * Decodes a JWT and validates its signature and expiration.
 *
 * @param string $token
 * @return array|null
 * @throws Exception
 */
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

/**
 * Returns a new instance of the Cast utility class.
 *
 * @return Cast
 */
function cast(): Cast
{
    return app(Cast::class);
}

/**
 * Returns a new instance of the Mailer service.
 *
 * @return Mailer
 */
function mailer(?string $url = null, ?string $user = null, ?string $pass = null, ?int $port = null): Mailer
{
    $url ??= env("MAILER_URL");
    $user ??= env("MAILER_USER");
    $pass ??= env("MAILER_PASS");
    $port ??= env("MAILER_PORT", 587);

    return app(Mailer::class, compact("url", "user", "pass", "port"));
}

/**
 * Returns a new query builder instance using the configured PDO.
 *
 * @return Query
 */
function query(?PDO $pdo): Query
{
    return app(Query::class, [$pdo ?? app(PDO::class)]);
}

/**
 * Returns a new instance of the validation rules provider.
 *
 * @return Validate
 */
function validate(): Validate
{
    return app(Validate::class);
}
