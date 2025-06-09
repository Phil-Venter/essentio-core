<?php

class Application
{
    public static string $basePath;
    public static Container $container;

    public static function http(string $basePath): void
    {
        static::initCommon($basePath);

        static::$container->once(Session::class, Session::create(...));
        static::$container->once(Jwt::class, Jwt::create(...));
        static::$container->once(Request::class, Request::create(...));
        static::$container->once(Response::class);
        static::$container->once(Router::class);

        if (class_exists(Authenticated::class, true)) {
            static::$container->once(Authenticated::class, Authenticated::create(...));
        }

        if (class_exists(Cast::class, true)) {
            static::$container->once(Cast::class);
        }

        if (class_exists(Validate::class, true)) {
            static::$container->once(Validate::class);
        }
    }

    public static function cli(string $basePath): void
    {
        static::initCommon($basePath);
        static::$container->once(Argument::class, Argument::create(...));
    }

    protected static function initCommon(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();

        static::$container->once(Environment::class);
        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));

        static::$container->once(PDO::class, function (
            ?string $dsn = null,
            ?string $user = null,
            ?string $pass = null
        ) {
            $env = static::$container->resolve(Environment::class);
            return new PDO($dsn ?? $env->get("DB_DSN"), $user ?? $env->get("DB_USER"), $pass ?? $env->get("DB_PASS"));
        });

        if (class_exists(Mailer::class, true)) {
            static::$container->bind(Mailer::class, Mailer::create(...));
        }

        if (class_exists(Query::class, true)) {
            static::$container->bind(Query::class, Query::create(...));
        }
    }

    public static function fromBase(string $path): string
    {
        return static::$basePath . "/" . ltrim($path, "/");
    }

    public static function run(): void
    {
        $request = static::$container->resolve(Request::class);
        $response = static::$container->resolve(Response::class);

        try {
            static::$container->resolve(Router::class)->dispatch($request, $response)->send();
        } catch (HttpException $e) {
            $status = $e->getCode() ?: 500;
            $response->setStatus($status)->setBody($e->getMessage())->send();
        } catch (Throwable) {
            $response->setStatus(500)->setBody("Internal Server Error")->send();
        }
    }
}

class Argument
{
    public function __construct(
        public string $command = '',
        public array $arguments = [],
    ) {
    }

    public static function create(?array $argv = null): static
    {
        $argv ??= $_SERVER["argv"] ?? [];
        array_shift($argv);

        if (empty($argv)) {
            return new static();
        }

        $command = "";
        $arguments = [];

        while (($arg = array_shift($argv)) !== null) {
            if ($arg === "--") {
                $arguments = array_merge($arguments, array_map(static::cast(...), $argv));
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

                $arguments[$key] = static::cast($value);
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

                $arguments[$key] = static::cast($value);
                continue;
            }

            if (empty($command)) {
                $command = $arg;
            } else {
                $arguments[] = static::cast($arg);
            }
        }

        return new static($command, $arguments);
    }

    public function get(int|string $key): mixed
    {
        return $this->arguments[$key] ?? null;
    }

    protected static function cast(mixed $value): mixed
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

class Container
{
    public function __construct(
        public array $bindings = [],
        public array $cache = [],
    ) {
    }

    public function bind(string $abstract, callable|string|null $concrete = null): self
    {
        $concrete ??= $abstract;

        if (is_string($concrete) && !class_exists($concrete, true)) {
            throw new RuntimeException("Cannot bind [{$abstract}] to [{$concrete}].");
        }

        $this->bindings[$abstract] = $concrete;
        return $this;
    }

    public function once(string $abstract, callable|string|null $concrete = null): self
    {
        $this->cache[$abstract] = null;
        return $this->bind($abstract, $concrete);
    }

    /**
     * @template T
     * @param class-string<T> $abstract
     * @param array<string,mixed>|list<mixed> $dependencies
     * @return T
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

        $resolved = is_callable($this->bindings[$abstract])
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
    public function __construct(
        public array $data = [],
    ) {
    }

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
                    $lower === "true" => true,
                    $lower === "false" => false,
                    $lower === "null" => null,
                    is_numeric($value) => preg_match("/[e\.]/", $value) ? (float) $value : (int) $value,
                    default => $value,
                };
            }

            $this->data[$name] = $value;
        }

        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }
}

class HttpException extends Exception
{
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

    public static function create(int $status, ?string $message = null, ?Throwable $previous = null): static
    {
        return new static($message ?? (static::HTTP_STATUS[$status] ?? "Unknown Error"), $status, $previous);
    }
}

class Jwt
{
    public function __construct(
        protected string $secret,
    ) {
    }

    public static function create(?string $secret = null): static
    {
        return new static(
            $secret ?? (Application::$container->resolve(Environment::class)->get("JWT_SECRET") ?? "Essentio")
        );
    }

    public function encode(array $payload): string
    {
        $header = ["alg" => "HS256", "typ" => "JWT"];
        $segments = [$this->base64url_encode(json_encode($header)), $this->base64url_encode(json_encode($payload))];
        $signingInput = implode(".", $segments);
        $signature = $this->sign($signingInput);

        $segments[] = $this->base64url_encode($signature);
        return implode(".", $segments);
    }

    public function decode(string $token): array
    {
        [$header64, $payload64, $signature64] = explode(".", $token);
        $signingInput = "$header64.$payload64";
        $signature = $this->base64url_decode($signature64);

        if (!hash_equals($this->sign($signingInput), $signature)) {
            throw new RuntimeException("Invalid token signature");
        }

        $payload = json_decode($this->base64url_decode($payload64), true);

        if (isset($payload["exp"]) && time() > $payload["exp"]) {
            throw new RuntimeException("Token has expired");
        }

        return $payload;
    }

    protected function base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
    }

    protected function base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, "-_", "+/"));
    }

    protected function sign(string $input): string
    {
        return hash_hmac("sha256", $input, $this->secret, true);
    }
}

class Request
{
    public array $errors = [];

    public function __construct(
        public string $method,
        public int $port,
        public string $path,
        public array $query,
        public array $headers,
        public array $cookies,
        public array $files,
        public array $body,
        public array $parameters,
    ) {
    }

    public static function create(
        ?array $server = null,
        ?array $headers = null,
        ?array $query = null,
        ?array $post = null,
        ?array $cookies = null,
        ?array $files = null,
        ?string $body = null,
    ): static
    {
        $server ??= $_SERVER;
        $post ??= $_POST ?? [];
        $query ??= $_GET;
        $cookies ??= $_COOKIE;
        $files ??= $_FILES;
        $headers ??= function_exists("getallheaders") ? getallheaders() : [];
        $rawInput = $body ?? file_get_contents("php://input");

        $method = strtoupper($post["_method"] ?? ($server["REQUEST_METHOD"] ?? "GET"));
        $path = trim(parse_url($server["REQUEST_URI"] ?? "", PHP_URL_PATH) ?? "", "/");

        $hostHeader = $server["HTTP_HOST"] ?? null;
        if ($hostHeader && str_contains((string) $hostHeader, ":")) {
            [, $port] = explode(":", (string) $hostHeader, 2);
            $port = (int) $port;
        } else {
            $port = (int) ($server["SERVER_PORT"] ?? (empty($server["HTTPS"]) ? 80 : 443));
        }

        $contentType = explode(";", $headers["Content-Type"] ?? "", 2)[0];

        $parsedBody = match ($contentType) {
            "application/json" => json_decode($rawInput, true) ?? [],
            "application/xml", "text/xml" => ($xml = simplexml_load_string($rawInput))
                ? json_decode(json_encode($xml), true)
                : [],
            default => $post,
        };

        return new static($method, $port, $path, $query, $headers, $cookies, $files, $parsedBody, []);
    }

    public function get(string $field): mixed
    {
        return $this->parameters[$field] ?? ($this->query[$field] ?? null);
    }

    public function input(string $field): mixed
    {
        return in_array($this->method, ["GET", "HEAD", "OPTIONS", "TRACE"], true)
            ? $this->get($field)
            : $this->body[$field] ?? ($this->parameters[$field] ?? null);
    }

    public function sanitize(array $rules): array|false
    {
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

class Response
{
    public function __construct(
        public int $status = 200,
        public array $headers = [],
        public bool|float|int|string|Stringable|null $body = null,
    ) {
    }

    public function setStatus(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function addHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $headers;
        return $this;
    }

    public function setBody(bool|float|int|string|Stringable|null $body): static
    {
        $this->body = $body;
        return $this;
    }

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

        echo (string) $this->body;
    }
}

class Router
{
    protected const LEAF = "\x00LEAF_NODE";
    protected const PARAM = "\x00PARAMETER";

    public function __construct(
        protected array $middleware = [],
        protected array $routes = [],
    ) {
    }

    public function middleware(callable $middleware): static
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function add(string $method, string $path, callable $handler, array $middleware = []): static
    {
        $path = trim((string) preg_replace("#/+#", "/", $path), "/");
        $node = &$this->routes;
        $params = [];

        foreach (explode("/", $path) as $segment) {
            if (str_starts_with($segment, ":")) {
                $node = &$node[static::PARAM];
                $params[] = substr($segment, 1);
            } else {
                $node = &$node[$segment];
            }
        }

        $node[static::LEAF][$method] = [$params, $middleware, $handler];
        return $this;
    }

    public function dispatch(Request $req, Response $res): Response
    {
        [$values, $routes] = $this->match($this->routes, explode("/", $req->path)) ?? throw HttpException::create(404);

        if (!isset($routes[$req->method])) {
            throw HttpException::create(405);
        }

        [$params, $middleware, $handler] = $routes[$req->method];
        $req->parameters = array_combine($params, $values);

        foreach (array_reverse($this->middleware) as $mw) {
            $handler = fn($req, $res) => $mw($req, $res, $handler);
        }

        foreach (array_reverse($middleware) as $mw) {
            $handler = fn($req, $res) => $mw($req, $res, $handler);
        }

        $result = $handler($req, $res);
        return $result instanceof Response ? $result : $res;
    }

    protected function match(array $node, array $segments, array $params = []): ?array
    {
        if (!$segments) {
            return $node[static::LEAF] ?? null ? [$params, $node[static::LEAF]] : null;
        }

        $segment = array_shift($segments);

        if (isset($node[$segment]) && ($found = $this->match($node[$segment], $segments, $params))) {
            return $found;
        }

        if (isset($node[static::PARAM])) {
            return $this->match($node[static::PARAM], $segments, [...$params, $segment]);
        }

        return null;
    }
}

class Session
{
    protected const FLASH_OLD = "\x00FLASH_OLD";
    protected const FLASH_NEW = "\x00FLASH_NEW";
    protected const CSRF_KEY = "\x00CSRF_KEY";

    public static function create(): static
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION[static::FLASH_OLD] = $_SESSION[static::FLASH_NEW] ?? [];
        $_SESSION[static::FLASH_NEW] = [];

        return new static();
    }

    public function set(string $key, mixed $value): mixed
    {
        return $_SESSION[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function setFlash(string $key, mixed $value): mixed
    {
        return $_SESSION[static::FLASH_NEW][$key] = $value;
    }

    public function getFlash(string $key): mixed
    {
        return $_SESSION[static::FLASH_OLD][$key] ?? null;
    }

    public function getCsrf(): string
    {
        return $_SESSION[static::CSRF_KEY] ??= bin2hex(random_bytes(32));
    }

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
    public function __construct(
        public ?string $template = null,
        public array $segments = [],
        public ?self $layout = null,
        public array $stack = [],
    ) {
    }

    protected function layout(string $template): void
    {
        $this->layout = new static($template);
    }

    protected function yield(string $name): ?string
    {
        return $this->segments[$name] ?? null;
    }

    protected function segment(string $name, ?string $value = null): void
    {
        if (func_num_args() === 2) {
            $this->segments[$name] = $value;
        } else {
            $this->stack[] = $name;
            ob_start();
        }
    }

    protected function end(): void
    {
        $name = array_pop($this->stack);
        $this->segments[$name] = ob_get_clean();
    }

    public function render(array $data = []): string
    {
        $content = (function (array $data) {
            ob_start();
            extract($data);
            include $this->template;
            return ob_get_clean();
        })($data);

        if ($this->layout !== null) {
            $this->segments["content"] = $content;
            $this->layout->segments = $this->segments;
            return $this->layout->render();
        }

        return $content;
    }
}

/**
 * @template T
 * @param class-string<T> $abstract
 * @return T
 */
function app(string $abstract): object
{
    return Application::$container->resolve($abstract);
}

/**
 * @template T
 * @param class-string<T> $abstract
 * @param array<string,mixed>|list<mixed> $dependencies
 * @return T
 */
function map(string $abstract, array $dependencies = []): object
{
    return Application::$container->resolve($abstract, $dependencies);
}

function bind(string $abstract, callable|string|null $concrete = null): void
{
    Application::$container->bind($abstract, $concrete);
}

function once(string $abstract, callable|string|null $concrete = null): void
{
    Application::$container->once($abstract, $concrete);
}

function base(string $path): string
{
    return Application::fromBase($path);
}

function env(string $key): mixed
{
    return app(Environment::class)->get($key);
}

function arg(int|string $key): mixed
{
    return app(Argument::class)->get($key);
}

function command(string $name, callable $handle): void
{
    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    exit(is_int($result = $handle($argument)) ? $result : 0);
}

function request(string $key = ''): mixed
{
    return func_num_args() ? app(Request::class) : app(Request::class)->get($key);
}

function input(string $field): mixed
{
    return app(Request::class)->input($field);
}

function sanitize(array $rules): array|false
{
    return app(Request::class)->sanitize($rules);
}

function session(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->get($key) : app(Session::class)->set($key, $value);
}

function flash(string $key, mixed $value = null): mixed
{
    return func_num_args() === 1 ? app(Session::class)->getFlash($key) : app(Session::class)->setFlash($key, $value);
}

function csrf(string $csrf = ''): string|bool
{
    return func_num_args() ? app(Session::class)->verifyCsrf($csrf) : app(Session::class)->getCsrf();
}

function jwt(array|string $payload): array|string
{
    return is_string($payload) ? app(Jwt::class)->decode($payload) : app(Jwt::class)->encode($payload);
}

function middleware(callable $middleware): void
{
    app(Router::class)->middleware($middleware);
}

function get(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("GET", $path, $handle, $middleware);
}

function post(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("POST", $path, $handle, $middleware);
}

function put(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("PUT", $path, $handle, $middleware);
}

function patch(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("PATCH", $path, $handle, $middleware);
}

function delete(string $path, callable $handle, array $middleware = []): void
{
    app(Router::class)->add("DELETE", $path, $handle, $middleware);
}

function render(string $template, array $data = []): string
{
    return map(Template::class, [$template])->render($data);
}

function redirect(string $uri, int $status = 302): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Location" => $uri]);
}

function html(string $html, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/html"])
        ->setBody($html);
}

function json(mixed $data, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "application/json"])
        ->setBody(json_encode($data));
}

function text(string $text, int $status = 200): Response
{
    return app(Response::class)
        ->setStatus($status)
        ->addHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}

function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}

function throw_if(bool $condition, Throwable|string $e): void
{
    if ($condition) {
        throw $e instanceof Throwable ? $e : new Exception($e);
    }
}
