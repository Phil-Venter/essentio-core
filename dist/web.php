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

return function (array $registry): void {
    foreach ($registry as $prefix => $path) {
        // skip invalid syntax
        if (!is_string($prefix) || !is_string($path)) {
            continue;
        }

        // Immediate inclusion for individual files
        if (is_file($path)) {
            if (is_readable($path)) {
                require_once $path;
            }

            continue;
        }

        // Normalize prefix and base directory
        $prefix = rtrim($prefix, '\\') . '\\';
        $path = rtrim($path, '/') . '/';

        // Register class autoloader
        spl_autoload_register(function ($class) use ($prefix, $path): void {
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = $path . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file) && is_readable($file)) {
                require_once $file;
            }
        });
    }
};

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
            "application/xml", "text/xml" => ($xml = simplexml_load_string($rawInput))
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

class Session
{
    protected const FLASH_OLD = "__flashold__";

    protected const FLASH_NEW = "__flashnew__";

    protected const CSRF_KEY = "__csrfkey__";

    /**
     * Start the session and prepare flash data for the request.
     */
    public static function create(?SessionHandler $sessionHandler = null): static
    {
        if ($sessionHandler instanceof SessionHandler) {
            session_set_save_handler($sessionHandler);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params([
                "lifetime" => 0,
                "path" => "/",
                "domain" => "",
                "secure" => !empty($_SERVER["HTTPS"]),
                "httponly" => true,
                "samesite" => "Lax",
            ]);

            session_start();
        }

        $_SESSION[static::FLASH_OLD] = $_SESSION[static::FLASH_NEW] ?? [];
        $_SESSION[static::FLASH_NEW] = [];
        return new static();
    }

    /**
     * Set a session value.
     */
    public function set(string $key, mixed $value): mixed
    {
        return $_SESSION[$key] = $value;
    }

    /**
     * Get a session value.
     */
    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * Set flash data for the next request.
     */
    public function setFlash(string $key, mixed $value): mixed
    {
        return $_SESSION[static::FLASH_NEW][$key] = $value;
    }

    /**
     * Get flash data from the previous request.
     */
    public function getFlash(string $key): mixed
    {
        return $_SESSION[static::FLASH_OLD][$key] ?? null;
    }

    /**
     * Get or generate a CSRF token.
     */
    public function getCsrf(): string
    {
        return $_SESSION[static::CSRF_KEY] ??= bin2hex(random_bytes(32));
    }

    /**
     * Validate and rotate a CSRF token.
     */
    public function verifyCsrf(string $csrf): bool
    {
        if ($valid = hash_equals($_SESSION[static::CSRF_KEY] ?? "", $csrf)) {
            $_SESSION[static::CSRF_KEY] = bin2hex(random_bytes(32));
        }

        return $valid;
    }
}

class Template
{
    protected array $segments = [];

    protected ?self $layout = null;

    protected array $stack = [];

    public function __construct(protected readonly string $template) {}

    /**
     * Set a parent layout template.
     */
    public function layout(string $template): void
    {
        $this->layout = new static($template);
    }

    /**
     * Get the content of a named segment.
     */
    public function yield(string $name): ?string
    {
        return $this->segments[$name] ?? null;
    }

    /**
     * Start or set a segment's content.
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
     */
    public function end(): void
    {
        if ($this->stack === []) {
            throw new FrameworkException("No segment started");
        }

        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    /**
     * Render the view and optional layout.
     *
     * @param array<string, mixed> $data
     */
    public function render(array $data = []): string
    {
        if (!file_exists($this->template)) {
            throw new FrameworkException("Template file not found");
        }

        $content =
            (function (array $data): string|false {
                ob_start();
                extract($data);
                include $this->template;
                return ob_get_clean();
            })($data) ?:
            "";

        if ($this->layout instanceof static) {
            $this->segments["content"] = $content;
            $this->layout->segments = $this->segments;
            return $this->layout->render();
        }

        return $content;
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
 * Get or set a session value.
 */
function session(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->get($key) : app(Session::class)->set($key, $value);
}

/**
 * Get or set a flash session value.
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
    return func_num_args() !== 0 ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

/**
 * Render a PHP template to string.
 *
 * @param array<string,mixed> $data
 */
function render(string $template, array $data = []): string
{
    return make(Template::class, [$template])->render($data);
}

/**
 * Render a view and return an HTML response.
 *
 * @param array<string,mixed> $data
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}
