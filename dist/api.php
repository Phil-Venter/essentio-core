<?php

class Application
{
    public static ?string $basePath = null;

    /**
     * Bootstrap the application with minimal dependencies.
     */
    public static function base(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/") . "/";

        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(static::fromBase(".env")));
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
        if (static::$basePath === null) {
            throw new FrameworkException("Application base path not initialized.");
        }

        return static::$basePath . ltrim($path, "/");
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
                $response
                    ->setStatus($throwable->getCode() ?: 500)
                    ->setBody($throwable->getMessage())
                    ->send();
            } else {
                error_log($throwable->getMessage());
                $response->setStatus(500)->setBody("Internal Server Error")->send();
            }
        }
    }
}

final class Container
{
    protected const BOUND_CLASS = "__new__";

    protected const BOUND_FACTORY = "__call__";

    protected const BOUND_ALIAS = "__alias__";

    private static $instance;

    private array $bindings = [];

    private array $cache = [];

    /**
     * Get the container singleton instance.
     */
    public static function instance(): static
    {
        return static::$instance ??= new self();
    }

    /**
     * Bind a class or factory to the container.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @param callable():T|T|class-string<T>|string|null $concrete
     */
    public function bind(string $id, callable|object|string|null $concrete = null): void
    {
        $concrete ??= $id;

        switch (true) {
            case is_string($concrete) && isset($this->bindings[$concrete]):
                $this->bindings[$id] = [self::BOUND_ALIAS, $concrete];
                break;
            case is_string($concrete) && class_exists($concrete, true):
                $this->bindings[$id] = [self::BOUND_CLASS, $concrete];
                break;
            case is_callable($concrete):
                $this->bindings[$id] = [self::BOUND_FACTORY, $concrete];
                break;
            case is_object($concrete):
                $this->cache[$id] = $concrete;
                break;
            default:
                throw new FrameworkException(sprintf("Service [%s] cannot be bound.", $id));
        }
    }

    /**
     * Bind a singleton to the container.
     *
     * @template T of object
     * @param class-string<T>|string $id
     * @param callable():T|T|class-string<T>|null $concrete
     */
    public function once(string $id, callable|object|string|null $concrete = null): void
    {
        $this->cache[$id] = null;
        $this->bind($id, $concrete);
    }

    /**
     * Resolve a class or binding from the container.
     *
     * @template T of object
     * @template U of class-string<T>|string
     * @param U $id
     * @param array<string,mixed>|list<mixed> $dependencies
     * @return (U is class-string<T> ? T : object)
     */
    public function get(string $id, array $dependencies = []): object
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        if (!array_key_exists($id, $this->bindings)) {
            if (class_exists($id, true)) {
                try {
                    return new $id(...$dependencies);
                } catch (Throwable $e) {
                    throw new FrameworkException(sprintf("Service [%s] could not be instantiated.", $id), 0, $e);
                }
            }

            throw new FrameworkException(sprintf("Service [%s] is not bound and cannot be instantiated.", $id));
        }

        [$type, $binding] = $this->bindings[$id];

        if ($type === self::BOUND_ALIAS) {
            return $this->get($binding, $dependencies);
        }

        try {
            $resolved = $type === self::BOUND_CLASS ? new $binding(...$dependencies) : $binding($this, ...$dependencies);
        } catch (Throwable $throwable) {
            throw new FrameworkException(sprintf("Service [%s] could not be instantiated.", $id), 0, $throwable);
        }

        if (!is_object($resolved)) {
            throw new FrameworkException(sprintf("Service [%s] did not resolve to an object.", $id));
        }

        if (array_key_exists($id, $this->cache)) {
            $this->cache[$id] = $resolved;
        }

        return $resolved;
    }
}

class Environment
{
    public function __construct(protected array $data = []) {}

    /**
     * Load and parse environment variables from a .env file.
     */
    public static function create(?string $file = null): static
    {
        $file ??= Application::fromBase(".env");

        if (!file_exists($file)) {
            return new static();
        }

        $data = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === "" || $line[0] === "#" || !str_contains($line, "=")) {
                continue;
            }

            /** @psalm-suppress PossiblyUndefinedArrayOffset */
            [$key, $value] = explode("=", $line, 2);
            $data[trim($key)] = static::autoCast(trim($value));
        }

        return new static($data);
    }

    /**
     * Get a value from the environment data.
     */
    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Convert a string to a native type if possible.
     */
    protected static function autoCast(string $value): mixed
    {
        if (preg_match('/^(["\']).*\1$/', $value)) {
            return substr($value, 1, -1);
        }

        $lower = strtolower($value);

        return match (true) {
            $lower === "true" => true,
            $lower === "false" => false,
            $lower === "null" => null,
            $lower === "" => null,
            is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
            default => $value,
        };
    }
}

class FrameworkException extends Exception {}

class HttpException extends FrameworkException
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
     */
    public static function create(int $status, ?string $message = null, ?Throwable $throwable = null): static
    {
        return new static($message ?? (static::HTTP_STATUS[$status] ?? "Unknown Error"), $status, $throwable);
    }
}

class Request
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
        public array $parameters,
    ) {}

    /**
     * Create a Request from global or custom input.
     *
     * @param array<string,mixed>|null $server
     * @param array<string,string>|null $headers
     * @param array<string,mixed>|null $query
     * @param array<string,mixed>|null $post
     * @param array<string,mixed>|null $cookies
     * @param array<string,mixed>|null $files
     */
    public static function create(
        ?array $server = null,
        ?array $headers = null,
        ?array $query = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
        ?string $body = null,
    ): static {
        $server ??= $_SERVER;
        $post ??= $_POST;
        $query ??= $_GET;
        $cookies ??= $_COOKIE;
        $files ??= $_FILES;
        $headers ??= function_exists("getallheaders") ? getallheaders() : [];
        $rawInput = $body ?? file_get_contents("php://input") ?: "";

        if (is_bool($headers)) {
            $headers = [];
        }

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
        foreach ($files as $file) {
            if (!is_array($file["name"] ?? null)) {
                if (($file["error"] ?? null) === UPLOAD_ERR_OK) {
                    $flatFiles[] = $file;
                }

                continue;
            }

            /** @psalm-suppress PossiblyInvalidArgument */
            $counter = count($file["name"]);

            /** @psalm-suppress PossiblyInvalidArgument */
            for ($i = 0; $i < $counter; $i++) {
                if ($file["error"][$i] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $temp = [];
                foreach ($file as $key => $vals) {
                    $temp[$key] = $vals[$i];
                }

                $flatFiles[] = $temp;
            }
        }

        $parsedBody = match ($contentType) {
            "application/json" => json_decode($rawInput, true) ?? [],
            "application/xml", "text/xml" => ($xml = simplexml_load_string($rawInput, null, LIBXML_NONET))
                ? json_decode(json_encode($xml, JSON_THROW_ON_ERROR), true)
                : [],
            default => $post,
        };

        return new static($method, $port, $path, $query, $contentType, $headers, $cookies, $flatFiles, $parsedBody, []);
    }

    /**
     * Get a query or path parameter.
     */
    public function get(string $field): mixed
    {
        return $this->parameters[$field] ?? ($this->query[$field] ?? null);
    }

    /**
     * Get a value from the request body or parameters.
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
     * @param array<T,callable>|array<T,list<callable>> $rules
     * @return array<T,mixed>|false
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
            } catch (ValidationException $e) {
                $this->errors[$field] = $e->getMessage();
            }
        }

        return $this->errors === [] ? $sanitized : false;
    }
}

class Response
{
    public int $status = 200;

    public array $headers = [];

    public bool|float|int|string|Stringable|null $body = null;

    /**
     * Set the HTTP status code.
     */
    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Add headers to the response.
     *
     * @param array<string,string|list<string>> $headers
     */
    public function addHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Replace all response headers.
     *
     * @param array<string,string|list<string>> $headers
     */
    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Set the response body.
     */
    public function setBody(bool|float|int|string|Stringable|null $body): static
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Send the HTTP response to the client.
     */
    public function send(bool $flush = false): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($this->status);
        foreach ($this->headers as $key => $value) {
            foreach ((array) $value as $i => $v) {
                header(sprintf("%s: %s", $key, $v), $i === 0);
            }
        }

        header_remove("X-Powered-By");

        if (!in_array($this->status, [204, 304], true)) {
            echo (string) $this->body;
        }

        if (!$flush) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
            return;
        }

        $flags = PHP_OUTPUT_HANDLER_REMOVABLE | PHP_OUTPUT_HANDLER_FLUSHABLE;

        foreach (ob_get_status(true) as $stat) {
            if (($stat['del'] ?? false) || (($stat['flags'] ?? 0) & $flags) === $flags) {
                @ob_end_flush();
            }
        }

        flush();
    }
}

class Router
{
    protected const LEAF = "__leafnode__";

    protected const PARAM = "__parameter__";

    /** @var list<callable> */
    protected array $middleware = [];

    protected string $prefix = "";

    protected array $routes = [];

    protected array $lookup = [];

    /**
     * Register middleware for current route scope.
     *
     * @param callable(Request, callable(Request): Response): Response $middleware
     */
    public function middleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Group routes under a common prefix and middleware.
     *
     * @param callable(): void $group
     */
    public function group(string $prefix, callable $group): void
    {
        [$oldPrefix, $oldMiddleware] = [$this->prefix, $this->middleware];
        $this->prefix .= $prefix;
        $group();
        [$this->prefix, $this->middleware] = [$oldPrefix, $oldMiddleware];
    }

    /**
     * Register a route handler for a method and path.
     *
     * @param callable(Request):mixed $handler
     * @param list<callable(Request,callable):Response> $middleware
     */
    public function route(string $method, string $path, callable $handler, array $middleware = []): void
    {
        $path = trim((string) preg_replace("#/+#", "/", $this->prefix . $path), "/");
        /** @psalm-suppress UnsupportedPropertyReferenceUsage */
        $node = &$this->routes;
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

        $node[static::LEAF][$method] = [$params, array_merge($this->middleware, $middleware), $handler];
    }

    /**
     * Match a request and execute the route handler.
     */
    public function dispatch(Request $request, Response $response): Response
    {
        $segments = explode("/", $request->path);
        $paramValues = [];
        $node = $this->routes;

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

        $method = $request->method;
        if ($request->method === "HEAD" && !isset($node[self::LEAF]["HEAD"]) && isset($node[self::LEAF]["GET"])) {
            $method = "GET";
        }

        if (!isset($node[static::LEAF])) {
            throw HttpException::create(404);
        }

        if (!isset($node[static::LEAF][$method])) {
            throw HttpException::create(405);
        }

        [$params, $middleware, $handler] = $node[static::LEAF][$method];
        $request->parameters = array_combine($params, $paramValues);

        foreach (array_reverse($middleware) as $mw) {
            $handler = fn(Request $request) => $mw($request, $handler);
        }

        $result = $handler($request);

        if ($result instanceof Response) {
            if ($request->method === "HEAD") {
                return $result->setBody("");
            }

            return $result;
        }

        if ($result instanceof Stringable || is_scalar($result)) {
            if ($request->method === "HEAD") {
                return $response->addHeaders(["Content-Length" => (string) mb_strlen((string) $result)]);
            }

            return $response->setBody((string) $result);
        }

        throw HttpException::create(204);
    }
}

class ValidationException extends FrameworkException {}

final readonly class Jwt
{
    public function __construct(private string $secret, private ?string $issuer = null) {}

    /**
     * Create a new Jwt instance from environment values.
     */
    public static function create(Environment $environment): static
    {
        $secret = (string) ($environment->get("JWT_SECRET") ?? ($environment->get("APP_KEY") ?? ""));

        if ($secret === "" || strlen($secret) < 16) {
            throw new FrameworkException("JWT secret not configured or too short.");
        }

        return new self($secret, $environment->get("JWT_ISSUER"));
    }

    /**
     * Encode a payload into a JWT string.
     *
     * @param array<string,mixed> $payload
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
     * @return array<string,mixed>
     */
    public function decode(string $token): array
    {
        $parts = explode(".", $token);
        if (count($parts) !== 3) {
            throw new FrameworkException("Invalid token format");
        }

        [$header64, $payload64, $signature64] = $parts;
        $signature = $this->decodeBase64($signature64);

        $header = json_decode($this->decodeBase64($header64), true);

        if (!is_array($header) || ($header["alg"] ?? null) !== "HS256") {
            throw new FrameworkException("Unsupported or missing algorithm");
        }

        if (!hash_equals($this->sign(sprintf("%s.%s", $header64, $payload64)), $signature)) {
            throw new FrameworkException("Invalid token signature");
        }

        $payload = json_decode($this->decodeBase64($payload64), true);

        if (!is_array($payload)) {
            throw new FrameworkException("Invalid payload format");
        }

        if (($this->issuer ?? null) !== ($payload["iss"] ?? null)) {
            throw new FrameworkException("Invalid issuer");
        }

        if (isset($payload["exp"]) && time() > (int) $payload["exp"]) {
            throw new FrameworkException("Token has expired");
        }

        if (isset($payload["iat"]) && time() < (int) $payload["iat"]) {
            throw new FrameworkException("Token not valid yet");
        }

        if (isset($payload["nbf"]) && time() < (int) $payload["nbf"]) {
            throw new FrameworkException("Token not valid yet");
        }

        return $payload;
    }

    /**
     * Encode data to base64url format.
     */
    private function encodeBase64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    /**
     * Decode data from base64url format.
     */
    private function decodeBase64(string $data): string
    {
        if (($remainder = strlen($data) % 4) !== 0) {
            $data .= str_repeat("=", 4 - $remainder);
        }

        $out = base64_decode(strtr($data, "-_", "+/"), true);

        if ($out === false) {
            throw new FrameworkException("Invalid base64url segment");
        }

        return $out;
    }

    /**
     * Sign input string using HMAC-SHA256.
     */
    private function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}

/**
 * Resolve a class from the container.
 *
 * @template T of object
 * @template U of class-string<T>|string
 * @param U $id
 * @return (U is class-string<T> ? T : object)
 */
function app(string $id): object
{
    return Container::instance()->get($id);
}

/**
 * Instantiate a class with dependencies.
 *
 * @template T of object
 * @template U of class-string<T>|string
 * @param U $id
 * @param array<string,mixed>|list<mixed> $dependencies
 * @return (U is class-string<T> ? T : object)
 */
function make(string $id, array $dependencies = []): object
{
    return Container::instance()->get($id, $dependencies);
}

/**
 * Register a binding into the container.
 *
 * @template T of object
 * @param class-string<T>|string $id
 * @param callable():T|T|class-string<T>|string|null $concrete
 */
function bind(string $id, callable|string|null $concrete = null): void
{
    Container::instance()->bind($id, $concrete);
}

/**
 * Register a singleton into the container.
 *
 * @template T of object
 * @param class-string<T>|string $id
 * @param callable():T|T|class-string<T>|null $concrete
 */
function once(string $id, callable|string|null $concrete = null): void
{
    Container::instance()->once($id, $concrete);
}

/**
 * Get the absolute path from base path.
 */
function base_path(string $path): string
{
    return Application::fromBase($path);
}

/**
 * Get environment variable from .env file.
 */
function env(string $key): mixed
{
    return app(Environment::class)->get($key);
}

/**
 * Conditionally throw an exception.
 *
 * @throws Throwable
 */
function throw_if(bool $condition, Throwable|string $e): void
{
    if ($condition) {
        throw $e instanceof Throwable ? $e : new Exception($e);
    }
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
    return func_num_args() !== 0 ? app(Request::class)->get($key) : app(Request::class);
}

/**
 * Get an input field from the request body or parameters.
 */
function input(string $field): mixed
{
    return app(Request::class)->input($field);
}

/**
 * Sanitize and validate user input.
 *
 * @template T as string
 * @param array<T,callable>|array<T,list<callable>> $rules
 * @param callable(array<string,list<string>>):void $callback
 * @return array<T,mixed>|false
 */
function sanitize(array $rules, callable $callback): array|false
{
    if (!($data = app(Request::class)->sanitize($rules))) {
        $callback(app(Request::class)->errors);
    }

    return $data;
}

/**
 * Register middleware globally or scoped within a group.
 */
function middleware(callable $middleware): void
{
    app(Router::class)->middleware($middleware);
}

/**
 * Define a route group with shared prefix.
 */
function group(string $prefix, callable $group): void
{
    app(Router::class)->group($prefix, $group);
}

/**
 * Register a GET route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function get(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("GET", $path, $handler, $middleware);
}

/**
 * Register a POST route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function post(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("POST", $path, $handler, $middleware);
}

/**
 * Register a PUT route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function put(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("PUT", $path, $handler, $middleware);
}

/**
 * Register a PATCH route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function patch(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("PATCH", $path, $handler, $middleware);
}

/**
 * Register a DELETE route.
 *
 * @param callable(Request):mixed $handler
 * @param list<callable(Request,callable):Response> $middleware
 */
function delete(string $path, callable $handler, callable ...$middleware): void
{
    /** @var list<callable(Request,callable):Response> $middleware */
    app(Router::class)->route("DELETE", $path, $handler, $middleware);
}

/**
 * Create a redirect response.
 */
function redirect(string $uri, int $status = 302): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Location" => $uri]);
}

/**
 * Convert an array or object to an xml string.
 */
function data_to_xml(array|object $data, string $rootElement = "root", ?SimpleXMLElement $xml = null): string
{
    if (!$xml instanceof SimpleXMLElement) {
        $xml = new SimpleXMLElement(sprintf('<?xml version="1.0"?><%s></%s>', $rootElement, $rootElement));
    }

    foreach ((array) $data as $key => $value) {
        if (is_numeric($key)) {
            $key = "item";
        }

        if (is_array($value) || is_object($value)) {
            data_to_xml($value, $key, $xml->addChild($key));
        } else {
            $xml->addChild($key, htmlspecialchars((string) $value));
        }
    }

    return (string) ($xml->asXML() ?: "");
}

/**
 * Create an XML response.
 */
function xml(array|object $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/xml"])
        ->setBody(data_to_xml($data));
}

/**
 * Create a JSON response.
 */
function json(mixed $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/json"])
        ->setBody(json_encode($data, JSON_THROW_ON_ERROR));
}

/**
 * Create an HTML response.
 */
function html(string $html, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/html"])
        ->setBody($html);
}

/**
 * Create a plain text response.
 */
function text(string $text, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}

/**
 * Encode or decode a JWT payload.
 *
 * @template T of array<string,mixed>|string
 * @param T $payload
 * @return (T is string ? array : string)
 */
function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}
