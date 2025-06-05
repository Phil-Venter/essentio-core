<?php

class Application
{
    public static string $basePath;
    public static Container $container;

    public static function http(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();

        static::$container->once(Environment::class);
        static::$container->once(Session::class, Session::create(...));
        static::$container->once(Request::class, Request::create(...));
        static::$container->once(Response::class);
        static::$container->once(Router::class);

        $env = static::$container->resolve(Environment::class);
        $env->load(static::fromBase(".env"));

        static::$container->once(Jwt::class, fn(?string $secret = null): Jwt => new Jwt($secret ?? $env->get("JWT_SECRET", "Essentio")));
    }

    public static function cli(string $basePath): void
    {
        static::$basePath = rtrim($basePath, "/");
        static::$container = new Container();

        static::$container->once(Environment::class);
        static::$container->once(Argument::class, Argument::create(...));

        static::$container->resolve(Environment::class)->load(static::fromBase(".env"));
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

    public function get(int|string $key, mixed $default = null): mixed
    {
        return $this->arguments[$key] ?? $default;
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

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
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

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->parameters[$field] ?? ($this->query[$field] ?? $default);
    }

    public function input(string $field, mixed $default = null): mixed
    {
        return in_array($this->method, ["GET", "HEAD", "OPTIONS", "TRACE"], true)
            ? $this->get($field, $default)
            : $this->body[$field] ?? ($this->parameters[$field] ?? $default);
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

    protected array $middleware = [];
    protected array $routes = [];

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

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set_flash(string $key, mixed $value): void
    {
        $_SESSION[static::FLASH_NEW][$key] = $value;
    }

    public function get_flash(string $key): mixed
    {
        return $_SESSION[static::FLASH_OLD][$key] ?? null;
    }

    public function get_csrf(): string
    {
        return $_SESSION[static::CSRF_KEY] ??= bin2hex(random_bytes(32));
    }

    public function verify_csrf(string $csrf): bool
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

    protected function yield(string $name, string $default = ''): string
    {
        return $this->segments[$name] ?? $default;
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
            $this->layout->setSegments($this->segments);
            return $this->layout->render($data);
        }

        return $content;
    }

    protected function setSegments(array $segments): void
    {
        $this->segments = $segments;
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

class Query implements Stringable
{
    protected bool $distinct = false;
    protected array $columns = [];
    protected array $groupBy = [];
    protected object $from;
    protected array $joins = [];
    protected object $wheres;
    protected object $havings;
    protected ?string $orderBy = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected object $unions;

    public function __construct(
        protected PDO $pdo,
    ) {
        $this->from = (object) ["sql" => "", "data" => []];
        $this->wheres = (object) ["sql" => [], "data" => []];
        $this->havings = (object) ["sql" => [], "data" => []];
        $this->unions = (object) ["sql" => [], "data" => []];
    }

    public function distinct(bool $on = true): static
    {
        $this->distinct = $on;
        return $this;
    }

    public function select(string|array ...$columns): static
    {
        if (is_array($columns[0])) {
            $columns = $columns[0];
        }

        if (array_is_list($columns)) {
            $columns = array_combine($columns, $columns);
        }

        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    public function from(callable|string $from, ?string $alias = null): static
    {
        if (!is_callable($from)) {
            $this->from->sql = $this->quote($from) . ($alias ? " AS " . $this->quote($alias) : "");
            return $this;
        }

        $alias ??= "sub";
        $from($subQuery = new static($this->pdo));
        [$sql, $data] = $subQuery->compileSelectArray();
        $this->from->sql = "($sql) AS " . $this->quote($alias);
        $this->from->data = $data;

        return $this;
    }

    public function join(
        string $table,
        ?string $first = null,
        ?string $operator = null,
        ?string $second = null,
        string $type = '',
    ): static
    {
        $type = strtoupper(trim($type ?: "INNER"));

        if (in_array($type, ["CROSS", "NATURAL"])) {
            $this->joins[] = "$type JOIN " . $this->quote($table);
            return $this;
        }

        if ($operator !== null && strtolower($first ?? "") === "using") {
            $columns = array_map([$this, "quote"], array_map("trim", explode(",", $operator)));
            $this->joins[] = "$type JOIN {$this->quote($table)} USING (" . implode(", ", $columns) . ")";
            return $this;
        }

        if ($operator !== null && $second === null) {
            $second = $operator;
            $operator = "=";
        }

        $extract = fn($sql): array => preg_match('/^(.+?)\s+AS\s+(.+)$/i', (string) $sql, $m) ? [$m[1], $m[2]] : [$sql, null];
        [$joinTable, $joinAlias] = $extract($table);

        if ($first === null || $second === null) {
            [$mainTable, $mainAlias] = $extract($this->from->sql);
            $first ??= ($mainAlias ?? $mainTable) . ".id";
            $second ??= ($joinAlias ?? $joinTable) . "." . $mainTable . "_id";
        }

        $as = $joinAlias ? " AS $joinAlias" : "";
        $quoteId = fn($identifier): string => implode(".", array_map([$this, "quote"], explode(".", (string) $identifier)));
        $this->joins[] = "{$type} JOIN {$this->quote($table)}{$as} ON {$quoteId($first)} {$operator} {$quoteId(
            $second
        )}";

        return $this;
    }

    public function whereRaw(string $sql, array $data = [], string $boolean = 'AND'): static
    {
        $this->wheres->sql[] = "$boolean $sql";
        $this->wheres->data = array_merge($this->wheres->data, $data);
        return $this;
    }

    public function orWhereRaw(string $sql, array $data = []): static
    {
        return $this->whereRaw($sql, $data, "OR");
    }

    public function where(
        callable|string $column,
        ?string $operator = null,
        mixed $value = null,
        string $boolean = 'AND',
    ): static
    {
        if (!empty(($compiled = $this->compileConditional("wheres", $column, $operator, $value, $boolean)))) {
            $this->wheres->sql[] = $compiled[0];
            $this->wheres->data = array_merge($this->wheres->data, $compiled[1]);
        }

        return $this;
    }

    public function orWhere(callable|string|self $column, ?string $operator = null, mixed $value = null): static
    {
        return $this->where($column, $operator, $value, "OR");
    }

    public function groupBy(string|array ...$columns): static
    {
        $this->groupBy = array_merge($this->groupBy, is_array($columns[0]) ? $columns[0] : $columns);
        return $this;
    }

    public function havingRaw(string $sql, array $data = [], string $boolean = 'AND'): static
    {
        $this->havings->sql[] = "$boolean $sql";
        $this->havings->data = array_merge($this->havings->data, $data);
        return $this;
    }

    public function orHavingRaw(string $sql, array $data = []): static
    {
        return $this->havingRaw($sql, $data, "OR");
    }

    public function having(
        callable|string $column,
        ?string $operator = null,
        mixed $value = null,
        string $boolean = 'AND',
    ): static
    {
        if (!empty(($compiled = $this->compileConditional("havings", $column, $operator, $value, $boolean)))) {
            $this->havings->sql[] = $compiled[0];
            $this->havings->data = array_merge($this->havings->data, $compiled[1]);
        }

        return $this;
    }

    public function orHaving(callable|string|self $column, ?string $operator = null, mixed $value = null): static
    {
        return $this->having($column, $operator, $value, "OR");
    }

    public function orderBy(string|array $column, string $direction = 'ASC'): static
    {
        if (is_array($column)) {
            $clauses = [];
            foreach ($column as $col => $dir) {
                $clauses[] = $this->quote($col) . " " . strtoupper((string) $dir);
            }

            $this->orderBy = implode(", ", $clauses);
        } else {
            $this->orderBy = $this->quote($column) . " " . strtoupper($direction);
        }

        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function union(callable $callback, string $type = ''): static
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, ["", "ALL", "DISTINCT"], true)) {
            throw new InvalidArgumentException("Invalid UNION type: $type");
        }

        $callback($query = new static($this->pdo));
        [$sql, $data] = $query->compileSelectArray();
        $this->unions->sql[] = "UNION {$type} ($sql)";
        $this->unions->data = array_merge($this->unions->data, $data);

        return $this;
    }

    public function get(): iterable
    {
        [$sql, $data] = $this->compileSelectArray();
        $stmt = $this->pdo->prepare($sql);

        foreach (array_values($data) as $idx => $value) {
            $stmt->bindValue(
                $idx + 1,
                $value,
                match (true) {
                    is_int($value) => PDO::PARAM_INT,
                    is_bool($value) => PDO::PARAM_BOOL,
                    is_null($value) => PDO::PARAM_NULL,
                    default => PDO::PARAM_STR,
                }
            );
        }

        $stmt->execute();
        return $stmt->getIterator();
    }

    public function first(): ?object
    {
        $this->limit(1);
        foreach ($this->get() as $row) {
            return (object) $row;
        }

        return null;
    }

    protected function compileSelectArray(): array
    {
        $columns = "*";
        if (!empty($this->columns)) {
            $columns = array_map(
                fn($alias, $col): string => $alias === $col
                    ? $this->quote($col)
                    : "{$this->quote($col)} AS {$this->quote($alias)}",
                array_keys($this->columns),
                $this->columns
            );

            $columns = implode(", ", $columns);
        }

        $sql = ($this->distinct ? "SELECT DISTINCT" : "SELECT") . " $columns FROM {$this->from->sql}";

        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }

        if ($where = preg_replace("/^\s*(AND|OR)\s*/", "", implode(" ", $this->wheres->sql))) {
            $sql .= " WHERE $where";
        }

        if (!empty($this->groupBy)) {
            $grouped = array_map(fn($col): string => $this->quote($col), $this->groupBy);
            $sql .= " GROUP BY " . implode(", ", $grouped);
        }

        if ($having = preg_replace("/^\s*(AND|OR)\s*/", "", implode(" ", $this->havings->sql))) {
            $sql .= " HAVING $having";
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY $this->orderBy";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT $this->limit";
            if ($this->offset !== null) {
                $sql .= " OFFSET $this->offset";
            }
        }

        if (!empty($this->unions->sql)) {
            $sql = "($sql) " . implode(" ", $this->unions->sql);
        }

        return [$sql, array_merge($this->from->data, $this->wheres->data, $this->havings->data, $this->unions->data)];
    }

    protected function compileConditional(
        string $typeParam,
        callable|string $column,
        ?string $operator = null,
        mixed $value = null,
        string $boolean = 'AND',
    ): array
    {
        if (is_callable($column)) {
            $column($sub = new self($this->pdo));
            return ($sql = preg_replace("/^\s*(AND|OR)\s*/", "", implode(" ", $this->{$typeParam}->sql)))
                ? ["$boolean ($sql)", $sub->{$typeParam}->data]
                : [];
        }

        $operator = strtoupper($operator ?? (is_array($value) ? "IN" : "="));

        if (is_callable($value)) {
            $value($sub = new self($this->pdo));
            [$sql, $data] = $sub->compileSelectArray();
            return empty($sql) ? [] : ["$boolean {$this->quote($column)} $operator ($sql)", $data];
        }

        if (is_null($value)) {
            return match ($operator) {
                "=", "IS", "IS NULL" => ["$boolean {$this->quote($column)} IS NULL", []],
                "!=", "<>", "IS NOT", "IS NOT NULL" => ["$boolean {$this->quote($column)} IS NOT NULL", []],
                default => throw new InvalidArgumentException("Unsupported NULL comparison operator: $operator"),
            };
        }

        if (is_array($value)) {
            $placeholders = fn($list) => implode(", ", array_fill(0, count($list), "?"));
            return match ($operator) {
                "BETWEEN", "NOT BETWEEN" => [
                    "$boolean {$this->quote($column)} $operator ? AND ?",
                    array_values($value),
                ],
                "IN", "NOT IN" => [
                    "$boolean {$this->quote($column)} $operator ({$placeholders($value)})",
                    array_values($value),
                ],
                default => throw new InvalidArgumentException("Unsupported operator '$operator' for array value."),
            };
        }

        if (is_string($value) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_\.]*$/', $value)) {
            return ["$boolean {$this->quote($column)} $operator {$this->quote($value)}", []];
        }

        return ["$boolean {$this->quote($column)} $operator ?", [is_bool($value) ? (int) $value : $value]];
    }

    protected function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function __toString(): string
    {
        [$sql] = $this->compileSelectArray();
        return (string) $sql;
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
 * @template T
 * @param class-string<T> $abstract
 * @return T
 */
function app(string $abstract = ''): object
{
    return func_num_args() ? Application::$container : Application::$container->resolve($abstract);
}

function map(string $abstract, array $dependencies = []): object
{
    return app()->resolve($abstract, $dependencies);
}

function bind(string $abstract, callable|string|null $concrete = null): void
{
    app()->bind($abstract, $concrete);
}

function once(string $abstract, callable|string|null $concrete = null): void
{
    app()->once($abstract, $concrete);
}

function env(string $key, mixed $default = null): mixed
{
    return app(Environment::class)->get($key, $default);
}

function arg(int|string $key, mixed $default = null): mixed
{
    return app(Argument::class)->get($key, $default);
}

function command(string $name, callable $handle): void
{
    $argument = app(Argument::class);

    if ($argument->command !== $name) {
        return;
    }

    exit(is_int($result = $handle($argument)) ? $result : 0);
}

function request(string $key = '', mixed $default = null): mixed
{
    return func_num_args() ? app(Request::class) : app(Request::class)->get($key, $default);
}

function input(string $field, mixed $default = null): mixed
{
    return app(Request::class)->input($field, $default);
}

function sanitize(array $rules): array|false
{
    return app(Request::class)->sanitize($rules);
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
        ->appendHeaders(["Content-Type" => "text/plain"])
        ->setBody($text);
}

function view(string $template, array $data = [], int $status = 200): Response
{
    return html(render($template, $data), $status);
}

function cast(): Cast
{
    return app(Cast::class);
}

function mailer(?string $url = null, ?string $user = null, ?string $pass = null, ?int $port = null): Mailer
{
    $url ??= env("MAILER_URL");
    $user ??= env("MAILER_USER");
    $pass ??= env("MAILER_PASS");
    $port ??= env("MAILER_PORT", 587);

    return app(Mailer::class, compact("url", "user", "pass", "port"));
}

function query(?PDO $pdo): Query
{
    return app(Query::class, [$pdo ?? app(PDO::class)]);
}

function validate(): Validate
{
    return app(Validate::class);
}
