<?php

class Application
{
    public static string $basePath;

    /**
     * Bootstrap the application with minimal dependancies.
     */
    public static function base(string $basePath): void
    {
        static::$basePath = rtrim($basePath, '/') . '/';

        Container::instance()->once(Environment::class, fn(): Environment => Environment::create(static::fromBase('.env')));
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
        return static::$basePath . ltrim($path, '/');
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
                $response->setStatus($throwable->getCode() ?: 500)->setBody($throwable->getMessage())->send();
            } else {
                error_log($throwable->getMessage());
                $response->setStatus(500)->setBody("Internal Server Error")->send();
            }
        }
    }
}

class Container
{
    protected static $instance;

    protected array $bindings = [];

    protected array $cache = [];

    /**
     * Get the container singleton instance.
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
            throw new FrameworkException(sprintf("Cannot bind [%s] to [%s].", $id, $concrete));
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
     * @param array<string,mixed>|list<mixed> $dependencies
     * @return T
     */
    public function get(string $id, array $dependencies = []): object
    {
        if (!isset($this->bindings[$id])) {
            if (class_exists($id, true)) {
                return new $id(...$dependencies);
            }

            throw new FrameworkException(sprintf("Service [%s] is not bound and cannot be instantiated.", $id));
        }

        $once = array_key_exists($id, $this->cache);

        if ($once && $this->cache[$id] !== null) {
            return $this->cache[$id];
        }

        $concrete = $this->bindings[$id];

        $resolved = is_string($concrete)
            ? new $concrete(...$dependencies)
            : $concrete(...$dependencies);

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

class Environment
{
    public function __construct(protected array $data = []) {}

    /**
     * Load and parse environment variables from a .env file.
     */
    public static function create(?string $file = null): static
    {
        $file ??= Application::fromBase('.env');

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
    protected static function autoCast(mixed $value): mixed
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
        echo (string) $this->body;

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

class Route
{
    /**
     * @var callable
     */
    public $handler;

    protected $setName;

    /**
     * @param list<string> $params
     * @param list<callable> $middleware
     */
    public function __construct(
        public readonly string $path,
        public readonly array $params,
        public array $middleware,
        callable $handler,
        callable $setName
    ) {
        $this->handler = $handler;
        $this->setName = $setName;
    }

    public function name(string $name): static
    {
        ($this->setName)($name, $this->path);
        return $this;
    }

    public function middleware(callable $middleware): static
    {
        $this->middleware[] = $middleware;
        return $this;
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
     * @param callable(Request): mixed $handler
     */
    public function route(string $method, string $path, callable $handler): Route
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

        $middleware = $this->middleware;

        return $node[static::LEAF][$method] = new Route($path, $params, $middleware, $handler, $this->setName(...));
    }

    /**
     * Assign a name to a route path.
     */
    protected function setName(string $name, string $path): void
    {
        $this->lookup[$name] = $path;
    }

    /**
     * Generate a URL for a named route with parameters.
     *
     * @param array<string, scalar> $params
     */
    public function makeUrlByName(string $name, array $params): string
    {
        if (!isset($this->lookup[$name])) {
            throw new FrameworkException(sprintf("Route named [%s] not found.", $name));
        }

        $url = $this->lookup[$name];

        foreach ($params as $key => $value) {
            $search = ":" . $key;
            if (str_contains($url, $search)) {
                $url = str_replace($search, rawurlencode((string) $value), $url);
                unset($params[$key]);
            }
        }

        if (str_contains($url, ":")) {
            throw new FrameworkException(sprintf("Missing parameter for route [%s].", $name));
        }

        $query = $params === [] ? "" : "?" . http_build_query($params);
        return sprintf("/%s%s", $url, $query);
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
            $handler = fn(Request $request) => $mw($request, $handler);
        }

        $result = $handler($request);

        if ($result instanceof Response) {
            return $result;
        }

        if (($result instanceof Stringable || is_scalar($result) || $result === null) && !in_array(trim((string) $result), ['', '0'], true)) {
            return $response->setBody($result);
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
        return new self($environment->get("JWT_SECRET") ?? "", $environment->get("JWT_ISSUER"));
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

        return base64_decode(strtr($data, "-_", "+/"));
    }

    /**
     * Sign input string using HMAC-SHA256.
     */
    private function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}

class Cast
{
    /**
     * Cast input to bool or throw.
     */
    public static function bool(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?bool {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            if (($bool = filter_var($input, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)) === null) {
                throw new ValidationException($message);
            }

            return $bool;
        };
    }

    /**
     * Cast input to DateTimeImmutable or throw.
     */
    public static function date(string $message = ""): Closure
    {
        return function (string $input) use ($message): DateTimeImmutable|null {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            try {
                return new DateTimeImmutable($input);
            } catch (Throwable $throwable) {
                throw new ValidationException($message, previous: $throwable);
            }
        };
    }

    /**
     * Cast input to enum value or throw.
     *
     * @param class-string<BackedEnum> $enumClass
     */
    public static function enum(string $enumClass, string $message = ""): Closure
    {
        if (!enum_exists($enumClass)) {
            throw new ValidationException("Invalid enum class: " . $enumClass);
        }

        if (!is_subclass_of($enumClass, BackedEnum::class)) {
            throw new ValidationException("Enum must be a backed enum");
        }

        return function (string $input) use ($enumClass, $message): ?BackedEnum {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            try {
                return $enumClass::from($input);
            } catch (Throwable $throwable) {
                throw new ValidationException($message, previous: $throwable);
            }
        };
    }

    /**
     * Cast input to float or throw.
     */
    public static function float(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?float {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($floatVal = filter_var($value, FILTER_VALIDATE_FLOAT)) === false) {
                throw new ValidationException($message);
            }

            return $floatVal;
        };
    }

    /**
     * Cast input to int or throw.
     */
    public static function int(string $message = ""): Closure
    {
        return function (string $input) use ($message): ?int {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($intVal = filter_var($value, FILTER_VALIDATE_INT)) === false) {
                throw new ValidationException($message);
            }

            return $intVal;
        };
    }

    /**
     * Cast input to int or float or throw.
     */
    public static function number(string $message = ""): Closure
    {
        return function (string $input) use ($message): int|float|null {
            if (($input = static::nullOnEmpty($input)) === null) {
                return null;
            }

            $value = static::normalizeNumber($input, $message);

            if (($intVal = filter_var($value, FILTER_VALIDATE_INT)) !== false) {
                return $intVal;
            }

            if (($floatVal = filter_var($value, FILTER_VALIDATE_FLOAT)) !== false) {
                return $floatVal;
            }

            throw new ValidationException($message);
        };
    }

    /**
     * Return string input, optionally trimmed.
     */
    public static function string(bool $trim = false): Closure
    {
        return fn(string $input): string => $trim ? trim($input) : $input;
    }

    /**
     * Return null if input is empty.
     */
    protected static function nullOnEmpty(string $input): mixed
    {
        return trim($input) === "" ? null : $input;
    }

    /**
     * Extract number from input string.
     */
    protected static function normalizeNumber(string $input, string $message): string
    {
        preg_match_all("/-?\d+(\.\d+)?/", $input, $matches);
        return empty($matches[0]) ? throw new ValidationException($message) : $matches[0][0];
    }
}

class Validate
{
    /**
     * Letters only.
     */
    public static function alpha(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match('/^[a-zA-Z]+$/', $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Letters, numbers, dashes, underscores.
     */
    public static function alphaDash(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match('/^[\w-]+$/', $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Letters and numbers only.
     */
    public static function alphaNum(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match('/^[a-zA-Z0-9]+$/', $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Valid email format.
     */
    public static function email(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (!filter_var($input, FILTER_VALIDATE_EMAIL)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must end with one of the given values.
     *
     * @param list<string> $suffixes
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

            throw new ValidationException($message);
        };
    }

    /**
     * Must be lowercase.
     */
    public static function lower(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtolower($input, "UTF-8") !== $input) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be uppercase.
     */
    public static function upper(string $message = ""): Closure
    {
        return function (?string $input) use ($message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strtoupper($input, "UTF-8") !== $input) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Minimum string length.
     */
    public static function minLength(int $min, string $message = ""): Closure
    {
        return function (?string $input) use ($min, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) < $min) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Maximum string length.
     */
    public static function maxLength(int $max, string $message = ""): Closure
    {
        return function (?string $input) use ($max, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (mb_strlen($input) > $max) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Matches regex pattern.
     *
     * @param non-empty-string $pattern
     */
    public static function regex(string $pattern, string $message = ""): Closure
    {
        return function (?string $input) use ($pattern, $message): ?string {
            if ($input === null) {
                return null;
            }

            if (in_array(preg_match($pattern, $input), [0, false], true)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Value must be between min and max.
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
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be greater than min.
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
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be greater than or equal to min.
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
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be less than max.
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
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Must be less than or equal to max.
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
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Input must be present and non-empty.
     */
    public static function required(string $message = ""): Closure
    {
        return function (mixed $input) use ($message): mixed {
            if (!isset($input) || (is_string($input) && trim($input) === "")) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }

    /**
     * Value must be in allowed set.
     *
     * @param list<mixed> $allowed
     */
    public static function inArray(array $allowed, bool $strict = true, string $message = ""): Closure
    {
        return function (mixed $input) use ($allowed, $strict, $message): mixed {
            if ($input === null) {
                return null;
            }

            if (!in_array($input, $allowed, $strict)) {
                throw new ValidationException($message);
            }

            return $input;
        };
    }
}

class HttpClient
{
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public bool|float|int|string|Stringable|null $body = null,
    ) {}

    /**
     * Send an HTTP request and return a Response.
     *
     * @param array<string,mixed> $headers
     */
    public static function request(string $method, string $url, array $headers = [], string $body = ""): static
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new FrameworkException("Unable to initialize cURL.");
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
            CURLOPT_HTTPHEADER => array_map(fn($k, $v): string => sprintf("%s: %s", $k, $v), array_keys($headers), $headers),
            CURLOPT_POSTFIELDS => $body,
        ]);

        try {
            $raw = curl_exec($curl);

            if ($raw === false) {
                throw new FrameworkException("Curl error: " . curl_error($curl));
            }

            $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
            $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            $headerText = substr((string) $raw, 0, $headerSize);
            $bodyText = substr((string) $raw, $headerSize);

            $headerLines = explode("\r\n", trim($headerText));
            $parsedHeaders = [];

            foreach ($headerLines as $headerLine) {
                if (str_contains($headerLine, ":")) {
                    /** @psalm-suppress PossiblyUndefinedArrayOffset */
                    [$key, $value] = explode(":", $headerLine, 2);
                    $parsedHeaders[trim($key)] = trim($value);
                }
            }

            return new static($statusCode, $parsedHeaders, $bodyText);
        } finally {
            curl_close($curl);
        }
    }
}

class Query
{
    protected string $bool = "AND";

    /** @var list<string> */
    protected array $columns = [];

    protected string $table = "";

    /** @var list<string> */
    protected array $where = [];

    /** @var list<mixed> */
    protected array $whereParams = [];

    /** @var list<string> */
    protected array $groupBys = [];

    /** @var list<string> */
    protected array $having = [];

    /** @var list<mixed> */
    protected array $havingParams = [];

    /** @var list<string> */
    protected array $orderBys = [];

    protected ?int $limit = null;

    protected ?int $offset = null;

    public function __construct(protected readonly ?PDO $pdo = null) {}

    /**
     * Use AND for the next condition.
     */
    public function and(): static
    {
        $this->bool = "AND";
        return $this;
    }

    /**
     * Use OR for the next condition.
     */
    public function or(): static
    {
        $this->bool = "OR";
        return $this;
    }

    /**
     * Consume current boolean operator.
     */
    protected function consumeBool(): string
    {
        $bool = $this->bool;
        $this->bool = "AND";
        return sprintf(" %s ", $bool);
    }

    /**
     * Select columns.
     */
    public function select(string ...$columns): static
    {
        $this->columns = array_merge($this->columns, array_values($columns));
        return $this;
    }

    /**
     * Set the table for the query.
     */
    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

    /**
     * Add a where clause.
     */
    public function where(string|Closure $column, ?string $operator = null, mixed $value = null): static
    {
        if ($column instanceof Closure) {
            $column($query = new static());
            return $this->whereRaw(sprintf("(%s)", $this->clean($query->where)), $query->whereParams);
        }

        if ($value === null) {
            $sql = match (true) {
                in_array(strtolower((string) $operator), ["=", "is"], true) => $column . " IS NULL",
                in_array(strtolower((string) $operator), ["!=", "<>", "is not", "not"], true) => $column . " IS NOT NULL",
                default => throw new FrameworkException("Invalid where condition."),
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
            return $this->whereRaw(sprintf("%s %s ?", $column, $operator), [$value]);
        }

        $operator ??= "IN";

        if (strtolower(trim($operator)) === "not") {
            $operator = "NOT IN";
        }

        if ($value instanceof Closure) {
            $value($query = new static());
            return $this->whereRaw(sprintf("%s %s (%s)", $column, $operator, $query->selectSql()), $query->getParams());
        }

        if (!is_array($value)) {
            throw new FrameworkException("Invalid where condition.");
        }

        if (mb_stripos($operator, "between") === false) {
            $placeholders = implode(", ", array_fill(0, count($value), "?"));
            return $this->whereRaw(sprintf("%s %s (%s)", $column, $operator, $placeholders), $value);
        }

        if (count($value) !== 2) {
            throw new FrameworkException("Invalid where condition.");
        }

        $value[0] = $formatValue($value[0]);
        $value[1] = $formatValue($value[1]);

        if (!is_scalar($value[0]) || !is_scalar($value[1])) {
            throw new FrameworkException("Invalid where condition.");
        }

        return $this->whereRaw(sprintf("%s %s ? AND ?", $column, $operator), $value);
    }

    /**
     * Add raw where condition.
     *
     * @param array<string,mixed>|list<mixed> $data
     */
    public function whereRaw(string $statement, array $data = []): static
    {
        $this->where[] = sprintf("%s %s", $this->consumeBool(), $statement);
        $this->whereParams = array_merge($this->whereParams, array_values($data));
        return $this;
    }

    /**
     * Add group by clauses.
     */
    public function groupBy(string ...$groupBys): static
    {
        $this->groupBys = array_merge($this->groupBys, array_values($groupBys));
        return $this;
    }

    /**
     * Add raw having clause.
     *
     * @param array<string,mixed> $data
     */
    public function havingRaw(string $statement, array $data = []): static
    {
        $this->having[] = sprintf("%s %s", $this->consumeBool(), $statement);
        $this->havingParams = array_merge($this->havingParams, array_values($data));
        return $this;
    }

    /**
     * Add order by clause.
     */
    public function orderBy(string $column, string $direction = "ASC"): static
    {
        $this->orderBys[] = sprintf("%s %s", $column, $direction);
        return $this;
    }

    /**
     * Set limit and optional offset.
     */
    public function limit(int $limit, ?int $offset = null): static
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    /**
     * Generate SQL for select.
     */
    protected function selectSql(): string
    {
        if ($this->table === "" || $this->table === "0") {
            throw new FrameworkException("Table name not specified for query.");
        }

        $sql = "SELECT " . implode(", ", $this->columns !== [] ? $this->columns : ["*"]) . (" FROM " . $this->table);

        if ($this->where !== []) {
            $sql .= " WHERE " . $this->clean($this->where);
        }

        if ($this->groupBys !== []) {
            $sql .= " GROUP BY " . implode(", ", $this->groupBys);

            if ($this->having !== []) {
                $sql .= " HAVING " . $this->clean($this->having);
            }
        }

        if ($this->orderBys !== []) {
            $sql .= " ORDER BY " . implode(", ", $this->orderBys);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;

            if ($this->offset !== null) {
                $sql .= " OFFSET " . $this->offset;
            }
        }

        return $sql;
    }

    /**
     * Remove leading boolean operator.
     *
     * @param list<string> $statements
     */
    protected function clean(array $statements): string
    {
        return (string) preg_replace("/^\s*(AND|OR)\s*/", "", implode(" ", $statements));
    }

    /**
     * Get combined query parameters.
     */
    protected function getParams(): array
    {
        return array_merge($this->whereParams, $this->havingParams);
    }

    /**
     * Execute and return query results.
     *
     * @return Generator<array<string,mixed>>
     */
    public function get(): Generator
    {
        if (!$this->pdo instanceof PDO) {
            throw new FrameworkException("No PDO to run query.");
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
     * @return array<string,mixed>
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
     * @param array<string,mixed> $data
     */
    public function insert(array $data): string|null
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if (array_is_list($data)) {
            throw new FrameworkException("Data must be associative array.");
        }

        if ($this->table === "" || $this->table === "0") {
            throw new FrameworkException("Table name not specified for insert.");
        }

        if (!$this->pdo instanceof PDO) {
            throw new FrameworkException("No PDO to run insert.");
        }

        $columnList = implode(", ", array_keys($data));
        $placeholders = implode(", ", array_fill(0, count($data), "?"));
        $sql = sprintf("INSERT INTO %s (%s) VALUES (%s)", $this->table, $columnList, $placeholders);

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        return $this->pdo->lastInsertId() ?: null;
    }

    /**
     * Update matching rows.
     *
     * @param array<string,mixed> $data
     */
    public function update(array $data): bool
    {
        /** @psalm-suppress TypeDoesNotContainType */
        if (array_is_list($data)) {
            throw new FrameworkException("Data must be associative array.");
        }

        if ($this->table === "" || $this->table === "0") {
            throw new FrameworkException("Table name not specified for update.");
        }

        if ($this->where === []) {
            throw new FrameworkException("Where clause missing for update.");
        }

        if (!$this->pdo instanceof PDO) {
            throw new FrameworkException("No PDO to run update.");
        }

        $columnList = implode(", ", array_map(fn($column): string => $column . " = ?", array_keys($data)));
        $sql = sprintf("UPDATE %s SET %s WHERE %s", $this->table, $columnList, $this->clean($this->where));

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([...array_values($data), ...$this->whereParams]);
    }

    /**
     * Delete matching rows.
     */
    public function delete(): bool
    {
        if ($this->table === "" || $this->table === "0") {
            throw new FrameworkException("Table name not specified for delete.");
        }

        if ($this->where === []) {
            throw new FrameworkException("Where clause missing for delete.");
        }

        if (!$this->pdo instanceof PDO) {
            throw new FrameworkException("No PDO to run delete.");
        }

        $sql = sprintf("DELETE FROM %s WHERE %s", $this->table, $this->clean($this->where));
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($this->whereParams);
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
 * @param array<string,mixed>|list<mixed> $dependencies
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
 * @param callable():T|class-string<T>|null $concrete
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
 * @param callable():T|class-string<T>|null $concrete
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
 */
function get(string $path, callable $handle): Route
{
    return app(Router::class)->route("GET", $path, $handle);
}

/**
 * Register a POST route.
 */
function post(string $path, callable $handle): Route
{
    return app(Router::class)->route("POST", $path, $handle);
}

/**
 * Register a PUT route.
 */
function put(string $path, callable $handle): Route
{
    return app(Router::class)->route("PUT", $path, $handle);
}

/**
 * Register a PATCH route.
 */
function patch(string $path, callable $handle): Route
{
    return app(Router::class)->route("PATCH", $path, $handle);
}

/**
 * Register a DELETE route.
 */
function delete(string $path, callable $handle): Route
{
    return app(Router::class)->route("DELETE", $path, $handle);
}

/**
 * Generate a named route URL.
 *
 * @param array<string,scalar> $params
 */
function named_url(string $name, array $params = []): string
{
    return app(Router::class)->makeUrlByName($name, $params);
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
function data_to_xml(array|object $data, string $rootElement = 'root', ?SimpleXMLElement $xml = null): string
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
            $xml->addChild($key, htmlspecialchars((string)$value));
        }
    }

    return (string) ($xml->asXML() ?: '');
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

/**
 * Send an HTTP request via HttpClient and return a Response.
 *
 * @param array<string,mixed> $headers
 */
function curl(string $method, string $url, array $headers = [], string $body = ""): HttpClient
{
    return HttpClient::request($method, $url, $headers, $body);
}

/**
 * Get a Query builder instance.
 */
function query(): Query
{
    return app(Query::class);
}
