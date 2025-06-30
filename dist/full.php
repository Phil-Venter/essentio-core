<?php

/**
 * @api
 */
final class Application
{
    /**
     * Bootstrap the application for CLI mode.
     *
     * @param string $basePath
     * @return void
     */
    public static function cli(string $basePath): void
    {
        Container::instance()->once(Argument::class, fn(): Argument => Argument::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Helper::class, fn(): Helper => Helper::create($basePath));
    }

    /**
     * Bootstrap the application for HTTP mode.
     *
     * @param string $basePath
     * @return void
     */
    public static function http(string $basePath): void
    {
        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(Container::instance()->get(Helper::class)));
        Container::instance()->once(Helper::class, fn(): Helper => Helper::create($basePath));
        Container::instance()->once(Jwt::class, fn(): Jwt => Jwt::create(Container::instance()->get(Environment::class)));
        Container::instance()->once(Request::class, fn(): Request => Request::create());
        Container::instance()->once(Response::class);
        Container::instance()->once(Session::class, fn(): Session => Session::create());
    }

    /**
     * Run the application and handle the request.
     *
     * @return void
     */
    public static function run(): void
    {
        $request = Container::instance()->get(Request::class);
        $response = Container::instance()->get(Response::class);

        try {
            Container::instance()->get(Router::class)->dispatch($request, $response)->send();
        } catch (HttpException $e) {
            $status = $e->getCode() ?: 500;
            $response->setStatus($status)->setBody($e->getMessage())->send();
        } catch (Throwable) {
            $response->setStatus(500)->setBody("Internal Server Error")->send();
        }
    }
}

/**
 * @api
 */
final class Argument
{
    public function __construct(public readonly string $command = "", protected array $arguments = []) {}

    /**
     * Parse CLI arguments into command and options.
     *
     * @param Helper $helper
     * @param list<string>|null $argv
     * @return static
     */
    public static function create(Helper $helper, ?array $argv = null): static
    {
        $argv ??= $_SERVER["argv"] ?? [];

        if (count($argv) <= 1) {
            return new static();
        }

        array_shift($argv);
        $command = "";
        $arguments = [];

        while (($arg = array_shift($argv)) !== null) {
            if ($arg === "--") {
                $arguments = array_merge($arguments, array_map($helper->autoCast(...), $argv));
                break;
            }

            if (str_starts_with((string) $arg, "--")) {
                $option = substr((string) $arg, 2);

                if (mb_stripos($option, "=") !== false) {
                    /** @psalm-suppress PossiblyUndefinedArrayOffset */
                    [$key, $value] = explode("=", $option, 2);
                } elseif (isset($argv[0]) && $argv[0][0] !== "-") {
                    $key = $option;
                    $value = array_shift($argv);
                } else {
                    $key = $option;
                    $value = true;
                }

                $arguments[$key] = $helper->autoCast($value);
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

                $arguments[$key] = $helper->autoCast($value);
                continue;
            }

            if (empty($command)) {
                $command = $arg;
            } else {
                $arguments[] = $helper->autoCast($arg);
            }
        }

        return new static($command, $arguments);
    }

    /**
     * Get an argument by key or index.
     *
     * @param int|string $key
     * @return mixed
     */
    public function get(int|string $key): mixed
    {
        return $this->arguments[$key] ?? null;
    }
}

/**
 * @api
 */
final class Container
{
    protected static $instance;

    protected array $bindings = [];

    protected array $cache = [];

    /**
     * Get the container singleton instance.
     *
     * @return static
     */
    public static function instance(): static
    {
        return static::$instance ??= new static();
    }

    /**
     * Bind a class or factory to the container.
     *
     * @template T
     * @param class-string<T> $id
     * @param callable():T|class-string<T>|null $concrete
     */
    public function bind(string $id, callable|string|null $concrete = null): void
    {
        /** @var string $concrete */
        if (is_string($concrete ??= $id) && !class_exists($concrete, true)) {
            throw new RuntimeException("Cannot bind [{$id}] to [{$concrete}].");
        }

        $this->bindings[$id] = $concrete;
    }

    /**
     * Bind a singleton to the container.
     *
     * @template T
     * @param class-string<T> $id
     * @param callable():T|class-string<T>|null $concrete
     */
    public function once(string $id, callable|string|null $concrete = null): void
    {
        $this->cache[$id] = null;
        $this->bind($id, $concrete);
    }

    /**
     * Resolve a class or binding from the container.
     *
     * @template T
     * @param class-string<T> $id
     * @param array<string, mixed>|list<mixed> $dependencies
     * @return T
     */
    public function get(string $id, array $dependencies = []): object
    {
        if (!isset($this->bindings[$id])) {
            if (class_exists($id, true)) {
                return new $id(...$dependencies);
            }

            throw new RuntimeException("Service [{$id}] is not bound and cannot be instantiated.");
        }

        $once = array_key_exists($id, $this->cache);

        if ($once && $this->cache[$id] !== null) {
            return $this->cache[$id];
        }

        $concrete = $this->bindings[$id];
        $resolved = is_string($concrete) ? new $concrete(...$dependencies) : $concrete(...$dependencies);

        if ($once) {
            $this->cache[$id] = $resolved;
        }

        /**
         * @template T
         * @var T $resolved
         */
        return $resolved;
    }
}

/**
 * @api
 */
final class Environment
{
    public function __construct(protected array $data = []) {}

    /**
     * Load and parse environment variables from a .env file.
     *
     * @param Helper $helper
     * @param string|null $file
     * @return static
     */
    public static function create(Helper $helper, ?string $file = null): static
    {
        if (!file_exists($file = $helper->fromBase($file ?? ".env"))) {
            return new static();
        }

        $data = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (empty($line) || $line[0] === "#" || !str_contains($line, "=")) {
                continue;
            }

            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            [$key, $value] = explode("=", $line, 2);
            $data[trim($key)] = $helper->autoCast(trim($value));
        }

        return new static($data);
    }

    /**
     * Get a value from the environment data.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}

/**
 * @api
 */
final class Helper
{
    public function __construct(protected string $basePath) {}

    /**
     * Create a new Helper with the given base path.
     *
     * @param string $basePath
     * @return static
     */
    public static function create(string $basePath): static
    {
        return new static(rtrim($basePath, "/"));
    }

    /**
     * Resolve a relative path from the base path.
     *
     * @param string $path
     * @return string
     */
    public function fromBase(string $path): string
    {
        return $this->basePath . "/" . ltrim($path, "/");
    }

    /**
     * Convert a string to a native type if possible.
     *
     * @param mixed $value
     * @return mixed
     */
    public function autoCast(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        if (preg_match('/^(["\']).*\1$/', $value)) {
            return substr($value, 1, -1);
        }

        $lower = strtolower($value);

        return match (true) {
            $lower === "true" => true,
            $lower === "false" => false,
            $lower === "null" => null,
            is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
            default => $value,
        };
    }
}

/**
 * @api
 */
final class HttpException extends Exception
{
    public const HTTP_STATUS = [
        // Success
        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        204 => "No Content",

        // Redirection
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        307 => "Temporary Redirect",
        308 => "Permanent Redirect",

        // Client Errors
        400 => "Bad Request",
        401 => "Unauthorized",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",

        // Server Errors
        500 => "Internal Server Error",
    ];

    /**
     * Create a new HTTP exception with status and optional message.
     *
     * @param int $status
     * @param string|null $message
     * @param Throwable|null $previous
     * @return static
     */
    public static function create(int $status, ?string $message = null, ?Throwable $previous = null): static
    {
        return new static($message ?? (static::HTTP_STATUS[$status] ?? "Unknown Error"), $status, $previous);
    }
}

/**
 * @api
 */
final class Jwt
{
    public function __construct(protected string $secret, protected ?string $issuer = null) {}

    /**
     * Create a new Jwt instance from environment values.
     *
     * @param Environment $env
     * @return static
     */
    public static function create(Environment $env): static
    {
        return new static($env->get("JWT_SECRET") ?? "", $env->get("JWT_ISSUER"));
    }

    /**
     * Encode a payload into a JWT string.
     *
     * @param array $payload
     * @return string
     */
    public function encode(array $payload): string
    {
        if ($this->issuer !== null) {
            $payload["iss"] = $this->issuer;
        }

        $segments = [$this->encodeBase64(json_encode(["alg" => "HS256", "typ" => "JWT"], JSON_THROW_ON_ERROR))];
        $segments[] = $this->encodeBase64(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->sign(implode(".", $segments));
        $segments[] = $this->encodeBase64($signature);

        return implode(".", $segments);
    }

    /**
     * Decode and validate a JWT string.
     *
     * @param string $token
     * @return array
     */
    public function decode(string $token): array
    {
        [$header64, $payload64, $signature64] = explode(".", $token);
        $signature = $this->decodeBase64($signature64);

        $header = json_decode($this->decodeBase64($header64), true);

        if (!is_array($header) || ($header["alg"] ?? null) !== "HS256") {
            throw new RuntimeException("Unsupported or missing algorithm");
        }

        if (!hash_equals($this->sign("$header64.$payload64"), $signature)) {
            throw new RuntimeException("Invalid token signature");
        }

        $payload = json_decode($this->decodeBase64($payload64), true);

        if (!is_array($payload)) {
            throw new RuntimeException("Invalid payload format");
        }

        if (($this->issuer ?? null) !== ($payload["iss"] ?? null)) {
            throw new RuntimeException("Invalid issuer");
        }

        if (isset($payload["exp"]) && time() > (int) $payload["exp"]) {
            throw new RuntimeException("Token has expired");
        }

        if (isset($payload["iat"]) && time() < (int) $payload["iat"]) {
            throw new RuntimeException("Token not valid yet");
        }

        if (isset($payload["nbf"]) && time() < (int) $payload["nbf"]) {
            throw new RuntimeException("Token not valid yet");
        }

        return $payload;
    }

    /**
     * Encode data to base64url format.
     *
     * @param string $data
     * @return string
     */
    protected function encodeBase64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * Decode data from base64url format.
     *
     * @param string $data
     * @return string
     */
    protected function decodeBase64(string $data): string
    {
        if ($remainder = strlen($data) % 4) {
            $data .= str_repeat("=", 4 - $remainder);
        }

        return base64_decode(strtr($data, "-_", "+/"));
    }

    /**
     * Sign input string using HMAC-SHA256.
     *
     * @param string $input
     * @return string
     */
    protected function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}

/**
 * @api
 */
final class Request
{
    public array $errors = [];

    public function __construct(
        public readonly string $method,
        public readonly int $port,
        public readonly string $path,
        protected array $query,
        public readonly string $contentType,
        public readonly array $headers,
        public readonly array $cookies,
        public readonly array $files,
        protected array $body,
        public array $parameters
    ) {}

    /**
     * Create a Request from global or custom input.
     *
     * @param array<string, mixed>|null $server
     * @param array<string, string>|null $headers
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $post
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     * @param string|null $body
     * @return static
     */
    public static function create(
        ?array $server = null,
        ?array $headers = null,
        ?array $query = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
        ?string $body = null
    ): static {
        $server ??= $_SERVER;
        $post ??= $_POST;
        $query ??= $_GET;
        $cookies ??= $_COOKIE;
        $files ??= $_FILES;
        $headers ??= function_exists("getallheaders") ? (getallheaders() ?: []) : [];
        $rawInput = $body ?? file_get_contents("php://input") ?: "";

        /** @psalm-suppress PossiblyInvalidArgument */
        $method = strtoupper($post["_method"] ?? ($server["REQUEST_METHOD"] ?? "GET"));
        $path = trim((string) parse_url($server["REQUEST_URI"] ?? "", PHP_URL_PATH), "/");

        $hostHeader = $server["HTTP_HOST"] ?? null;
        if ($hostHeader && str_contains((string) $hostHeader, ":")) {
            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            [, $port] = explode(":", (string) $hostHeader, 2);
            $port = (int) $port;
        } else {
            $port = (int) ($server["SERVER_PORT"] ?? (empty($server["HTTPS"]) ? 80 : 443));
        }

        $contentType = explode(";", $headers["Content-Type"] ?? "", 2)[0];

        $flatFiles = [];
        foreach ($files as $info) {
            if (!is_array($info["name"] ?? null)) {
                if (($info["error"] ?? null) === UPLOAD_ERR_OK) {
                    $flatFiles[] = $info;
                }

                continue;
            }

            /** @psalm-suppress PossiblyInvalidArgument */
            for ($i = 0; $i < count($info["name"]); $i++) {
                if ($info["error"][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $temp = [];
                foreach ($info as $key => $vals) {
                    $temp[$key] = $vals[$i];
                }

                $flatFiles[] = $temp;
            }
        }

        $parsedBody = match ($contentType) {
            "application/json" => json_decode($rawInput, true) ?? [],
            "application/xml", "text/xml" => ($xml = simplexml_load_string($rawInput))
                ? json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true)
                : [],
            default => $post,
        };

        return new static($method, $port, $path, $query, $contentType, $headers, $cookies, $flatFiles, $parsedBody, []);
    }

    /**
     * Get a query or path parameter.
     *
     * @param string $field
     * @return mixed
     */
    public function get(string $field): mixed
    {
        return $this->parameters[$field] ?? ($this->query[$field] ?? null);
    }

    /**
     * Get a value from the request body or parameters.
     *
     * @param string $field
     * @return mixed
     */
    public function input(string $field): mixed
    {
        return in_array($this->method, ["GET", "HEAD", "OPTIONS", "TRACE"], true)
            ? $this->get($field)
            : $this->body[$field] ?? ($this->parameters[$field] ?? null);
    }

    /**
     * Sanitize and validate input fields.
     *
     * @template T as string
     * @param array<T, callable>|array<T, list<callable>> $rules
     * @return array<T, mixed>|false
     */
    public function sanitize(array $rules): array|false
    {
        $this->errors = [];
        $sanitized = [];

        foreach ($rules as $field => $chain) {
            $value = $this->input($field);

            try {
                foreach ((array) $chain as $fn) {
                    $value = $fn($value);
                }

                $sanitized[$field] = $value;
            } catch (Throwable $e) {
                $this->errors[$field][] = $e->getMessage();
            }
        }

        return empty($this->errors) ? $sanitized : false;
    }
}

/**
 * @api
 */
final class Response
{
    public int $status = 200;

    public array $headers = [];

    public bool|float|int|string|Stringable|null $body = null;

    /**
     * Set the HTTP status code.
     *
     * @param int $status
     * @return static
     */
    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Add headers to the response.
     *
     * @param array<string, string|list<string>> $headers
     * @return static
     */
    public function addHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Replace all response headers.
     *
     * @param array<string, string|list<string>> $headers
     * @return static
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the response body.
     *
     * @param bool|float|int|string|Stringable|null $body
     * @return static
     */
    public function setBody(bool|float|int|string|Stringable|null $body): static
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Send the HTTP response to the client.
     *
     * @return void
     */
    public function send(): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->status);

        foreach ($this->headers as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $i => $v) {
                    header("{$key}: {$v}", $i === 0);
                }
            } else {
                header("{$key}: {$value}", true);
            }
        }

        header_remove("X-Powered-By");
        echo (string) $this->body;
    }
}

/**
 * @api
 */
interface RouteInterface
{
    public function name(string $name): static;

    public function middleware(callable $middleware): static;
}

/**
 * @api
 */
final class Router
{
    protected const LEAF = "\0LEAF_NODE";

    protected const PARAM = "\0PARAMETER";

    protected static array $middleware = [];

    protected static string $prefix = "";

    protected static array $routes = [];

    protected static array $lookup = [];

    /**
     * Register middleware for current route scope.
     *
     * @param callable(Request, callable(Request): Response): Response $middleware
     * @return void
     */
    public static function middleware(callable $middleware): void
    {
        static::$middleware[] = $middleware;
    }

    /**
     * Group routes under a common prefix and middleware.
     *
     * @param string $prefix
     * @param callable(): void $group
     * @return void
     */
    public static function group(string $prefix, callable $group): void
    {
        [$oldPrefix, $oldMiddleware] = [static::$prefix, static::$middleware];
        static::$prefix .= $prefix;
        $group();
        [static::$prefix, static::$middleware] = [$oldPrefix, $oldMiddleware];
    }

    /**
     * Register a route handler for a method and path.
     *
     * @param string $method
     * @param string $path
     * @param callable(Request): mixed $handler
     * @return RouteInterface
     */
    public static function route(string $method, string $path, callable $handler): RouteInterface
    {
        $path = trim((string) preg_replace("#/+#", "/", static::$prefix . $path), "/");
        /** @psalm-suppress UnsupportedPropertyReferenceUsage */
        $node = &static::$routes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                /** @psalm-suppress UnsupportedPropertyReferenceUsage */
                $node = &$node[static::PARAM];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        $middleware = static::$middleware;

        return $node[static::LEAF][$method] = new class ($path, $params, $middleware, $handler) implements RouteInterface {
            public function __construct(public string $path, public array $params, public array $middleware, public $handler) {}

            #[Override]
            public function name(string $name): static
            {
                Router::setName($name, $this->path);
                return $this;
            }

            #[Override]
            public function middleware(callable $middleware): static
            {
                $this->middleware[] = $middleware;
                return $this;
            }
        };
    }

    /**
     * Assign a name to a route path.
     *
     * @param string $name
     * @param string $path
     * @return void
     */
    public static function setName(string $name, string $path): void
    {
        static::$lookup[$name] = $path;
    }

    /**
     * Generate a URL for a named route with parameters.
     *
     * @param string $name
     * @param array<string, scalar> $params
     * @return string
     */
    public static function makeUrlByName(string $name, array $params): string
    {
        if (!isset(static::$lookup[$name])) {
            throw new InvalidArgumentException("Route named [{$name}] not found.");
        }

        $url = static::$lookup[$name];

        foreach ($params as $key => $value) {
            $search = ":" . $key;
            if (str_contains($url, $search)) {
                $url = str_replace($search, rawurlencode((string) $value), $url);
                unset($params[$key]);
            }
        }

        if (str_contains($url, ":")) {
            throw new InvalidArgumentException("Missing parameter for route [{$name}].");
        }

        $query = empty($params) ? "" : "?" . http_build_query($params);
        return "/{$url}{$query}";
    }

    /**
     * Match a request and execute the route handler.
     *
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public static function dispatch(Request $request, Response $response): Response
    {
        $segments = explode("/", $request->path);
        $paramValues = [];
        $node = static::$routes;

        while ($segments) {
            $segment = array_shift($segments);

            if (isset($node[$segment])) {
                $node = $node[$segment];
                continue;
            }

            if (isset($node[static::PARAM])) {
                $paramValues[] = $segment;
                $node = $node[static::PARAM];
                continue;
            }

            throw HttpException::create(404);
        }

        if (!isset($node[static::LEAF])) {
            throw HttpException::create(404);
        }

        if (!isset($node[static::LEAF][$request->method])) {
            throw HttpException::create(405);
        }

        $route = $node[static::LEAF][$request->method];
        $request->parameters = array_combine($route->params, $paramValues);
        $handler = $route->handler;

        foreach (array_reverse($route->middleware) as $mw) {
            $handler = fn(Request $req) => $mw($req, $handler);
        }

        $result = $handler($request);

        if ($result instanceof Response) {
            return $result;
        }

        if (($result instanceof Stringable || is_scalar($result)) && !empty(trim((string) $result))) {
            return $response->setBody($result);
        }

        throw HttpException::create(204);
    }
}

/**
 * @api
 */
final class Session
{
    protected const FLASH_OLD = "\0FLASH_OLD";

    protected const FLASH_NEW = "\0FLASH_NEW";

    protected const CSRF_KEY = "\0CSRF_KEY";

    /**
     * Start the session and initialize flash data.
     *
     * @return static
     */
    public static function create(): static
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[static::FLASH_OLD] = $_SESSION[static::FLASH_NEW] ?? [];
        $_SESSION[static::FLASH_NEW] = [];
        return new static();
    }

    /**
     * Set a session value.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function set(string $key, mixed $value): mixed
    {
        return $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Set flash data for the next request.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    public function setFlash(string $key, mixed $value): mixed
    {
        return $_SESSION[static::FLASH_NEW][$key] = $value;
    }

    /**
     * Get flash data from the previous request.
     *
     * @param string $key
     * @return mixed
     */
    public function getFlash(string $key): mixed
    {
        return $_SESSION[static::FLASH_OLD][$key] ?? null;
    }

    /**
     * Get or generate a CSRF token.
     *
     * @return string
     */
    public function getCsrf(): string
    {
        return $_SESSION[static::CSRF_KEY] ??= bin2hex(random_bytes(32));
    }

    /**
     * Validate and rotate a CSRF token.
     *
     * @param string $csrf
     * @return bool
     */
    public function verifyCsrf(string $csrf): bool
    {
        if ($valid = hash_equals($_SESSION[static::CSRF_KEY] ?? "", $csrf)) {
            $_SESSION[static::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $valid;
    }
}

/**
 * @api
 */
final class Template
{
    protected array $segments = [];

    protected ?self $layout = null;

    protected array $stack = [];

    public function __construct(protected ?string $template = null) {}

    /**
     * Set a parent layout template.
     *
     * @param string $template
     * @return void
     */
    public function layout(string $template): void
    {
        $this->layout = new static($template);
    }

    /**
     * Get the content of a named segment.
     *
     * @param string $name
     * @return string|null
     */
    public function yield(string $name): ?string
    {
        return $this->segments[$name] ?? null;
    }

    /**
     * Start or set a segment's content.
     *
     * @param string $name
     * @param string|null $value
     * @return void
     */
    public function segment(string $name, ?string $value = null): void
    {
        if ($value === null) {
            $this->stack[] = $name;
            ob_start();
        } else {
            $this->segments[$name] = $value;
        }
    }

    /**
     * End the current segment buffer.
     *
     * @return void
     */
    public function end(): void
    {
        if (empty($this->stack)) {
            throw new InvalidArgumentException("No segment started");
        }

        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    /**
     * Render the view and optional layout.
     *
     * @param array<string, mixed> $data
     * @return string
     */
    public function render(array $data = []): string
    {
        $content =
            (function (array $data) {
                ob_start();
                extract($data);
                include $this->template;
                return ob_get_clean();
            })($data) ?:
            "";

        if ($this->layout !== null) {
            $this->segments["content"] = $content;
            $this->layout->segments = $this->segments;
            return $this->layout->render();
        }

        return $content;
    }
}

/**
 * @api
 */
final class Cast
{
    /**
     * Cast input to bool or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function bool(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?bool {
            $input = static::nullOnEmpty($input);

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
     * Cast input to DateTimeImmutable or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function date(string $message = ""): Closure
    {
        return function (string $input) use ($message): DateTimeImmutable|null {
            $input = static::nullOnEmpty($input);

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
     * Cast input to enum value or throw.
     *
     * @param class-string<BackedEnum> $enumClass
     * @param string $message
     * @return Closure
     */
    public static function enum(string $enumClass, string $message = ""): Closure
    {
        if (!enum_exists($enumClass)) {
            throw new Exception("Invalid enum class: $enumClass");
        }

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new Exception("Enum must be a backed enum");
        }

        return function (string $input) use ($enumClass, $message): ?BackedEnum {
            $input = static::nullOnEmpty($input);

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
     * Cast input to float or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function float(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?float {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);
            $floatVal = filter_var($value, FILTER_VALIDATE_FLOAT);

            if ($floatVal === false) {
                throw new Exception($message);
            }

            return $floatVal;
        };
    }

    /**
     * Cast input to int or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function int(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?int {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);
            $intVal = filter_var($value, FILTER_VALIDATE_INT);

            if ($intVal === false) {
                throw new Exception($message);
            }

            return $intVal;
        };
    }

    /**
     * Cast input to int or float or throw.
     *
     * @param string $message
     * @return Closure
     */
    public static function number(string $message = ""): Closure
    {
        return function (string $input) use ($message): int|float|null {
            $input = static::nullOnEmpty($input);

            if ($input === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($intVal = filter_var($value, FILTER_VALIDATE_INT)) !== false) {
                return $intVal;
            }

            if (($floatVal = filter_var($value, FILTER_VALIDATE_FLOAT)) !== false) {
                return $floatVal;
            }

            throw new Exception($message);
        };
    }

    /**
     * Return string input, optionally trimmed.
     *
     * @param bool $trim
     * @return Closure
     */
    public static function string(bool $trim = false): Closure
    {
        return function (string $input) use ($trim): string {
            if ($trim) {
                return trim($input);
            }

            return $input;
        };
    }

    /**
     * Return null if input is empty.
     *
     * @param string $input
     * @return mixed
     */
    protected static function nullOnEmpty(string $input): mixed
    {
        if (trim($input) === "") {
            return null;
        }

        return $input;
    }

    /**
     * Extract number from input string.
     *
     * @param string $input
     * @param string $message
     * @return string
     */
    protected static function normalizeNumber(string $input, string $message): string
    {
        preg_match_all("/-?\d+(\.\d+)?/", $input, $matches);

        if (empty($matches[0])) {
            throw new Exception($message);
        }

        return $matches[0][0];
    }
}

/**
 * @api
 */
final class HttpClient
{
    /**
     * Send an HTTP request and return a Response.
     *
     * @param string $method
     * @param string $url
     * @param array $headers
     * @param string $body
     * @return Response
     */
    public static function request(string $method, string $url, array $headers = [], string $body = ""): Response
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new RuntimeException("Unable to initialize cURL.");
        }

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => array_map(fn($k, $v): string => "{$k}: {$v}", array_keys($headers), $headers),
            CURLOPT_POSTFIELDS => $body,
        ]);

        try {
            $raw = curl_exec($curl);

            if ($raw === false) {
                throw new RuntimeException("Curl error: " . curl_error($curl));
            }

            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            $headerText = substr((string) $raw, 0, $headerSize);
            $bodyText = substr((string) $raw, $headerSize);

            $response = new Response();
            $response->setStatus($statusCode);
            $response->setBody($bodyText);

            $headerLines = explode("\r\n", trim($headerText));
            $parsedHeaders = [];

            foreach ($headerLines as $line) {
                if (str_contains($line, ":")) {
                    /** @psalm-suppress PossiblyUndefinedArrayOffset */
                    [$key, $value] = explode(":", $line, 2);
                    $parsedHeaders[trim($key)] = trim($value);
                }
            }

            $response->addHeaders($parsedHeaders);
            return $response;
        } finally {
            curl_close($curl);
        }
    }
}

/**
 * @api
 */
final class Query
{
    protected string $bool = "AND";

    protected array $columns = [];

    protected string $table = "";

    protected array $where = [];

    protected array $whereParams = [];

    protected array $groupBys = [];

    protected array $having = [];

    protected array $havingParams = [];

    protected array $orderBys = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    public function __construct(protected ?PDO $pdo = null) {}

    /**
     * Use OR for the next condition.
     *
     * @return static
     */
    public function or(): static
    {
        $this->bool = "OR";
        return $this;
    }

    /**
     * Consume current boolean operator.
     *
     * @return string
     */
    protected function consumeBool(): string
    {
        $bool = $this->bool;
        $this->bool = "AND";
        return " $bool ";
    }

    /**
     * Select columns.
     *
     * @param array|string ...$columns
     * @return static
     */
    public function select(array|string ...$columns): static
    {
        $columns = array_values($columns);
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    /**
     * Set the table for the query.
     *
     * @param string $table
     * @return static
     */
    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Add a where clause.
     *
     * @param string|Closure $column
     * @param string|null $operator
     * @param mixed $value
     * @return static
     */
    public function where(string|Closure $column, ?string $operator = null, mixed $value = null): static
    {
        if ($column instanceof Closure) {
            $column($query = new static());
            return $this->whereRaw("({$this->clean($query->where)})", $query->whereParams);
        }

        if ($value === null) {
            $sql = match (true) {
                in_array(strtolower((string) $operator), ["=", "is"], true) => "{$column} IS NULL",
                in_array(strtolower((string) $operator), ["!=", "<>", "is not", "not"], true) => "{$column} IS NOT NULL",
                default => throw new InvalidArgumentException("Invalid where condition."),
            };

            return $this->whereRaw($sql);
        }

        $formatValue = fn($val) => match (true) {
            $val instanceof DateTimeInterface => $val->format("Y-m-d H:i:s"),
            $val instanceof Stringable => (string) $val,
            default => $val,
        };

        $value = $formatValue($value);

        if (is_scalar($value)) {
            $operator ??= "=";
            return $this->whereRaw("{$column} {$operator} ?", [$value]);
        }

        $operator ??= "IN";

        if (strtolower(trim($operator)) === "not") {
            $operator = "NOT IN";
        }

        if ($value instanceof Closure) {
            $value($query = new static());
            return $this->whereRaw("{$column} {$operator} ({$query->selectSql()})", $query->getParams());
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException("Invalid where condition.");
        }

        if (mb_stripos($operator, "between") === false) {
            $placeholders = implode(", ", array_fill(0, count($value), "?"));
            return $this->whereRaw("{$column} {$operator} ({$placeholders})", $value);
        }

        if (count($value) !== 2) {
            throw new InvalidArgumentException("Invalid where condition.");
        }

        $value[0] = $formatValue($value[0]);
        $value[1] = $formatValue($value[1]);

        if (!is_scalar($value[0]) || !is_scalar($value[1])) {
            throw new InvalidArgumentException("Invalid where condition.");
        }

        return $this->whereRaw("{$column} {$operator} ? AND ?", $value);
    }

    /**
     * Add raw where condition.
     *
     * @param string $statement
     * @param array $data
     * @return static
     */
    public function whereRaw(string $statement, array $data = []): static
    {
        $this->where[] = "{$this->consumeBool()} {$statement}";
        $this->whereParams = array_merge($this->whereParams, $data);
        return $this;
    }

    /**
     * Add group by clauses.
     *
     * @param array|string ...$groupBys
     * @return static
     */
    public function groupBy(array|string ...$groupBys): static
    {
        $groupBys = array_values($groupBys);
        $this->groupBys = array_merge($this->groupBys, $groupBys);
        return $this;
    }

    /**
     * Add raw having clause.
     *
     * @param string $statement
     * @param array $data
     * @return static
     */
    public function havingRaw(string $statement, array $data = []): static
    {
        $this->having[] = "{$this->consumeBool()} {$statement}";
        $this->havingParams = array_merge($this->havingParams, $data);
        return $this;
    }

    /**
     * Add order by clause.
     *
     * @param string $column
     * @param string $direction
     * @return static
     */
    public function orderBy(string $column, string $direction = "ASC"): static
    {
        $this->orderBys[] = "{$column} {$direction}";
        return $this;
    }

    /**
     * Set limit and optional offset.
     *
     * @param int $limit
     * @param int|null $offset
     * @return static
     */
    public function limit(int $limit, ?int $offset = null): static
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Generate SQL for select.
     *
     * @return string
     */
    protected function selectSql(): string
    {
        if (empty($this->table)) {
            throw new RuntimeException("Table name not specified for query.");
        }

        $sql = "SELECT " . implode(", ", $this->columns ?: ["*"]) . " FROM {$this->table}";

        if (!empty($this->where)) {
            $sql .= " WHERE {$this->clean($this->where)}";
        }

        if (!empty($this->groupBys)) {
            $sql .= " GROUP BY " . implode(", ", $this->groupBys);

            if (!empty($this->having)) {
                $sql .= " HAVING {$this->clean($this->having)}";
            }
        }

        if (!empty($this->orderBys)) {
            $sql .= " ORDER BY " . implode(", ", $this->orderBys);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";

            if ($this->offset !== null) {
                $sql .= " OFFSET {$this->offset}";
            }
        }

        return $sql;
    }

    /**
     * Remove leading boolean operator.
     *
     * @param array $statements
     * @return string
     */
    protected function clean(array $statements): string
    {
        return (string) preg_replace("/^\s*(AND|OR)\s*/", "", implode(" ", $statements));
    }

    /**
     * Get combined query parameters.
     *
     * @return array
     */
    protected function getParams(): array
    {
        return array_merge($this->whereParams, $this->havingParams);
    }

    /**
     * Execute and return query results.
     *
     * @psalm-return Generator<int, mixed, mixed, never>
     */
    public function get(): Generator
    {
        if ($this->pdo === null) {
            throw new RuntimeException("No PDO to run query.");
        }

        $stmt = $this->pdo->prepare($this->selectSql());
        $stmt->execute($this->getParams());

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            yield $row;
        }
    }

    /**
     * Get first result or empty array.
     *
     * @return array
     */
    public function first(): array
    {
        $this->limit = 1;

        foreach ($this->get() as $row) {
            return $row;
        }

        return [];
    }

    /**
     * Insert a new row and return ID.
     *
     * @param array $data
     * @return null|string
     */
    public function insert(array $data): string|null
    {
        if (array_is_list($data)) {
            throw new InvalidArgumentException("Data must be associative array.");
        }

        if (empty($this->table)) {
            throw new RuntimeException("Table name not specified for insert.");
        }

        if ($this->pdo === null) {
            throw new RuntimeException("No PDO to run insert.");
        }

        $columnList = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->pdo->lastInsertId() ?: null;
    }

    /**
     * Update matching rows.
     *
     * @param array $data
     * @return bool
     */
    public function update(array $data): bool
    {
        if (array_is_list($data)) {
            throw new InvalidArgumentException("Data must be associative array.");
        }

        if (empty($this->table)) {
            throw new RuntimeException("Table name not specified for update.");
        }

        if (empty($this->where)) {
            throw new RuntimeException("Where clause missing for update.");
        }

        if ($this->pdo === null) {
            throw new RuntimeException("No PDO to run update.");
        }

        $columnList = implode(", ", array_map(fn($column): string => "{$column} = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$columnList} WHERE {$this->clean($this->where)}";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([...array_values($data), ...$this->whereParams]);
    }

    /**
     * Delete matching rows.
     *
     * @return bool
     */
    public function delete(): bool
    {
        if (empty($this->table)) {
            throw new RuntimeException("Table name not specified for delete.");
        }

        if (empty($this->where)) {
            throw new RuntimeException("Where clause missing for delete.");
        }

        if ($this->pdo === null) {
            throw new RuntimeException("No PDO to run delete.");
        }

        $sql = "DELETE FROM {$this->table} WHERE {$this->clean($this->where)}";
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($this->whereParams);
    }
}

/**
 * @api
 */
final class Validate
{
    /**
     * Letters only.
     *
     * @param string $message
     * @return Closure
     */
    public static function alpha(string $message = ""): Closure
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
     * Letters, numbers, dashes, underscores.
     *
     * @param string $message
     * @return Closure
     */
    public static function alphaDash(string $message = ""): Closure
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
     * Letters and numbers only.
     *
     * @param string $message
     * @return Closure
     */
    public static function alphaNum(string $message = ""): Closure
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
     * Valid email format.
     *
     * @param string $message
     * @return Closure
     */
    public static function email(string $message = ""): Closure
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
     * Must end with one of the given values.
     *
     * @param array $suffixes
     * @param string $message
     * @return Closure
     */
    public static function endsWith(array $suffixes, string $message = ""): Closure
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
     * Must be lowercase.
     *
     * @param string $message
     * @return Closure
     */
    public static function lower(string $message = ""): Closure
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
     * Must be uppercase.
     *
     * @param string $message
     * @return Closure
     */
    public static function upper(string $message = ""): Closure
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
     * Minimum string length.
     *
     * @param int $min
     * @param string $message
     * @return Closure
     */
    public static function minLength(int $min, string $message = ""): Closure
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
     * Maximum string length.
     *
     * @param int $max
     * @param string $message
     * @return Closure
     */
    public static function maxLength(int $max, string $message = ""): Closure
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
     * Matches regex pattern.
     *
     * @param non-empty-string $pattern
     * @param string $message
     * @return Closure
     */
    public static function regex(string $pattern, string $message = ""): Closure
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
     * Value must be between min and max.
     *
     * @param DateTimeInterface|float|int $min
     * @param DateTimeInterface|float|int $max
     * @param string $message
     * @return Closure
     */
    public static function between(DateTimeInterface|float|int $min, DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use ($min, $max, $message): DateTimeInterface|float|int|null {
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
     * Must be greater than min.
     *
     * @param DateTimeInterface|float|int $min
     * @param string $message
     * @return Closure
     */
    public static function gt(DateTimeInterface|float|int $min, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (DateTimeInterface|float|int|null $input) use ($min, $message): DateTimeInterface|float|int|null {
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
     * Must be greater than or equal to min.
     *
     * @param DateTimeInterface|float|int $min
     * @param string $message
     * @return Closure
     */
    public static function gte(DateTimeInterface|float|int $min, string $message = ""): Closure
    {
        $min = $min instanceof DateTimeInterface ? $min->getTimestamp() : $min;

        return function (DateTimeInterface|float|int|null $input) use ($min, $message): DateTimeInterface|float|int|null {
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
     * Must be less than max.
     *
     * @param DateTimeInterface|float|int $max
     * @param string $message
     * @return Closure
     */
    public static function lt(DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use ($max, $message): DateTimeInterface|float|int|null {
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
     * Must be less than or equal to max.
     *
     * @param DateTimeInterface|float|int $max
     * @param string $message
     * @return Closure
     */
    public static function lte(DateTimeInterface|float|int $max, string $message = ""): Closure
    {
        $max = $max instanceof DateTimeInterface ? $max->getTimestamp() : $max;

        return function (DateTimeInterface|float|int|null $input) use ($max, $message): DateTimeInterface|float|int|null {
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
     * Input must be present and non-empty.
     *
     * @param string $message
     * @return Closure
     */
    public static function required(string $message = ""): Closure
    {
        return function (mixed $input) use ($message): mixed {
            if (!isset($input) || (is_string($input) && trim($input) === "")) {
                throw new Exception($message);
            }

            return $input;
        };
    }

    /**
     * Value must be in allowed set.
     *
     * @param array $allowed
     * @param bool $strict
     * @param string $message
     * @return Closure
     */
    public static function inArray(array $allowed, bool $strict = true, string $message = ""): Closure
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
 * Resolve a class from the container.
 *
 * @template T
 * @param class-string<T> $id
 * @return T
 */
function app(string $id): object
{
    return Container::instance()->get($id);
}

/**
 * Instantiate a class with dependencies.
 *
 * @template T
 * @param class-string<T> $id
 * @param array<string, mixed>|list<mixed> $dependencies
 * @return T
 */
function make(string $id, array $dependencies = []): object
{
    return Container::instance()->get($id, $dependencies);
}

/**
 * Register a binding into the container.
 *
 * @template T
 * @param class-string<T> $id
 * @param callable $concrete
 * @return void
 */
function bind(string $id, callable|string|null $concrete = null): void
{
    Container::instance()->bind($id, $concrete);
}

/**
 * Register a singleton into the container.
 *
 * @template T
 * @param class-string<T> $id
 * @param callable $concrete
 * @return void
 */
function once(string $id, callable|string|null $concrete = null): void
{
    Container::instance()->once($id, $concrete);
}

/**
 * Get the absolute path from base path.
 *
 * @param string $path
 * @return string
 */
function base_path(string $path): string
{
    return app(Helper::class)->fromBase($path);
}

/**
 * Get environment variable from .env file.
 *
 * @param string $key
 * @return mixed
 */
function env(string $key): mixed
{
    return app(Environment::class)->get($key);
}

/**
 * Get CLI argument by key.
 *
 * @param int|string $key
 * @return mixed
 */
function arg(int|string $key): mixed
{
    return app(Argument::class)->get($key);
}

/**
 * Register and execute a CLI command.
 *
 * @param string $name
 * @param callable $handle
 * @return void
 */
function command(string $name, callable $handle): void
{
    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    exit(is_int($result = $handle($argument)) ? $result : 0);
}

/**
 * Get the request instance or a specific key from it.
 *
 * @template T as string
 * @param T $key
 * @return (T is '' ? Request : mixed)
 */
function request(string $key = ""): mixed
{
    return func_num_args() ? app(Request::class)->get($key) : app(Request::class);
}

/**
 * Get an input field from the request body or parameters.
 *
 * @param string $field
 * @return mixed
 */
function input(string $field): mixed
{
    return app(Request::class)->input($field);
}

/**
 * Sanitize and validate user input.
 *
 * @template T as string
 * @param array<T, callable>|array<T, list<callable>> $rules
 * @param callable $callback
 * @return array<T, mixed>|false
 */
function sanitize(array $rules, callable $callback): array|false
{
    if (!($data = app(Request::class)->sanitize($rules))) {
        $callback(app(Request::class)->errors);
    }

    return $data;
}

/**
 * Get or set a session value.
 *
 * @param string $key
 * @param mixed $value
 * @return mixed
 */
function session(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->get($key) : app(Session::class)->set($key, $value);
}

/**
 * Get or set a flash session value.
 *
 * @param string $key
 * @param mixed $value
 * @return mixed
 */
function flash(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->getFlash($key) : app(Session::class)->setFlash($key, $value);
}

/**
 * Generate or verify a CSRF token.
 *
 * @template T as string
 * @param T $csrf
 * @return (T is '' ? string : bool)
 */
function csrf(string $csrf = ""): string|bool
{
    return func_num_args() ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

/**
 * Encode or decode a JWT payload.
 *
 * @template T of array|string
 * @param T $payload
 * @return (T is string ? array : string)
 */
function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}

/**
 * Register middleware globally or scoped within a group.
 *
 * @param callable $middleware
 * @return void
 */
function middleware(callable $middleware): void
{
    Router::middleware($middleware);
}

/**
 * Define a route group with shared prefix.
 *
 * @param string $prefix
 * @param callable $group
 * @return void
 */
function group(string $prefix, callable $group): void
{
    Router::group($prefix, $group);
}

/**
 * Register a GET route.
 *
 * @param string $path
 * @param callable $handle
 * @return RouteInterface
 */
function get(string $path, callable $handle): RouteInterface
{
    return Router::route("GET", $path, $handle);
}

/**
 * Register a POST route.
 *
 * @param string $path
 * @param callable $handle
 * @return RouteInterface
 */
function post(string $path, callable $handle): RouteInterface
{
    return Router::route("POST", $path, $handle);
}

/**
 * Register a PUT route.
 *
 * @param string $path
 * @param callable $handle
 * @return RouteInterface
 */
function put(string $path, callable $handle): RouteInterface
{
    return Router::route("PUT", $path, $handle);
}

/**
 * Register a PATCH route.
 *
 * @param string $path
 * @param callable $handle
 * @return RouteInterface
 */
function patch(string $path, callable $handle): RouteInterface
{
    return Router::route("PATCH", $path, $handle);
}

/**
 * Register a DELETE route.
 *
 * @param string $path
 * @param callable $handle
 * @return RouteInterface
 */
function delete(string $path, callable $handle): RouteInterface
{
    return Router::route("DELETE", $path, $handle);
}

/**
 * Generate a named route URL.
 *
 * @param string $name
 * @param array<string, scalar> $params
 * @return string
 */
function named_url(string $name, array $params = []): string
{
    return Router::makeUrlByName($name, $params);
}

/**
 * Render a PHP template to string.
 *
 * @param string $template
 * @param array<string, mixed> $data
 * @return string
 */
function render(string $template, array $data = []): string
{
    return make(Template::class, [$template])->render($data);
}

/**
 * Create a redirect response.
 *
 * @param string $uri
 * @param int $status
 * @return Response
 */
function redirect(string $uri, int $status = 302): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Location" => $uri]);
}

/**
 * Create an HTML response.
 *
 * @param string $html
 * @param int $status
 * @return Response
 */
function html(string $html, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/html"])
        ->setBody($html);
}

/**
 * Create a JSON response.
 *
 * @param mixed $data
 * @param int $status
 * @return Response
 */
function json(mixed $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/json"])
        ->setBody(json_encode($data, JSON_THROW_ON_ERROR));
}

/**
 * Create a plain text response.
 *
 * @param string $text
 * @param int $status
 * @return Response
 */
function text(string $text, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}

/**
 * Render a view and return an HTML response.
 *
 * @param string $template
 * @param array<string, mixed> $data
 * @param int $status
 * @return Response
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}

/**
 * Conditionally throw an exception.
 *
 * @param bool $condition
 * @param Throwable|string $e
 * @return void
 * @throws Throwable
 */
function throw_if(bool $condition, Throwable|string $e): void
{
    if ($condition) {
        throw $e instanceof Throwable ? $e : new Exception($e);
    }
}

/**
 * Send an HTTP request via HttpClient and return a Response.
 *
 * @param string $method  HTTP verb (e.g. 'GET', 'POST')
 * @param string $url     Full URL to request
 * @param array $headers  Associative array of headers (e.g. ['Accept' => 'application/json'])
 * @param string $body    Request body as a raw string
 * @return Response       Structured response with status, headers, and body
 */
function call(string $method, string $url, array $headers = [], string $body = ""): Response
{
    return HttpClient::request($method, $url, $headers, $body);
}

/**
 * Get a Query builder instance.
 *
 * @return Query
 */
function query(): Query
{
    return app(Query::class);
}
