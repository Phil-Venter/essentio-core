<?php

/**
 * Handles the initialization and execution of the application for both
 * web and CLI environments.
 */
class Application
{
	/** @var string */
	protected static string $basePath;

	/** @var Container */
	public static Container $container;

	/** @var bool */
	public static bool $isWeb;

	/**
	 * Initialize the application for HTTP requests.
	 *
	 * Sets up the container bindings for web-specific components,
	 * and ensures that session handling is configured.
	 *
	 * @param string $basePath
	 * @return void
	 */
	public static function http(string $basePath): void
	{
		static::$basePath = \rtrim($basePath, '/');
		static::$container = new Container;
		static::$isWeb = true;

		static::$container->bind(Environment::class, fn() => new Environment)->once = true;
		static::$container->bind(Request::class, fn() => Request::init())->once = true;
		static::$container->bind(Router::class, fn() => new Router)->once = true;

		if (\session_status() !== PHP_SESSION_ACTIVE) {
		    if (\function_exists('apcu_enabled') && \apcu_enabled()) {
		        \session_set_save_handler(new SessionHandler(3600), true);
		    }

		    \session_start();
		}
	}

	/**
	 * Initialize the application for CLI commands.
	 *
	 * Sets up the container bindings for CLI-specific components.
	 *
	 * @param string $basePath
	 * @return void
	 */
	public static function cli(string $basePath): void
	{
		static::$basePath = \rtrim($basePath, '/');
		static::$container = new Container;
		static::$isWeb = false;

		static::$container->bind(Environment::class, fn() => new Environment)->once = true;
		static::$container->bind(Argument::class, fn() => Argument::init())->once = true;
	}

	/**
	 * Resolve an absolute path based on the application's base directory.
	 *
	 * This method concatenates the stored base path with the provided relative path,
	 * then uses `realpath()` to return the absolute path. If the path cannot be resolved,
	 * it returns false.
	 *
	 * @param string $path
	 * @return string|false
	 */
	public static function fromBase(string $path): string|false
	{
		return \realpath(\sprintf('%s/%s', static::$basePath, $path));
	}

	/**
	 * Run the application.
	 *
	 * For web applications, it processes the request using the Router and
	 * sends the corresponding response. In case of an exception, a 500 error
	 * is returned.
	 *
	 * @return void
	 */
	public static function run(): void
	{
		if (!static::$isWeb) {
		    return;
		}

		try {
		    static::$container->get(Router::class)
		        ->run(static::$container->get(Request::class))
		        ->send();
		} catch (HttpException $e) {
		    (new Response)
		        ->withStatus($e->getCode())
		        ->withHeaders(['Content-Type' => 'text/html'])
		        ->withBody($e->getMessage())
		        ->send();
		} catch (\Throwable $e) {
		    (new Response)
		        ->withStatus(500)
		        ->withHeaders(['Content-Type' => 'text/plain'])
		        ->withBody('Something went wrong. Please try again later.')
		        ->send();
		}
	}
}

/**
 * Parses and holds command-line arguments including the command,
 * named parameters, and positional parameters.
 */
class Argument
{
	/** @var string */
	public protected(set) string $command = '';

	/** @var array<string, mixed> */
	public protected(set) array $named = [];

	/** @var list<mixed> */
	public protected(set) array $positional = [];

	/**
	 * Initializes and parses the command-line arguments.
	 *
	 * This method extracts the command, named, and positional arguments
	 * from the $_SERVER['argv'] array. It supports both long (--name)
	 * and short (-n) argument formats.
	 *
	 * @return static
	 */
	public static function init(): static
	{
		$argv = $_SERVER['argv'] ?? [];

		\array_shift($argv);

		$that = new static;

		while ($arg = \array_shift($argv)) {
		    if ($arg === '--') {
		        $that->positional = \array_merge($that->positional, $argv);
		        break;
		    }

		    if (\str_starts_with($arg, '--')) {
		        $name = \substr($arg, 2);

		        if (\str_contains($name, '=')) {
		            [$key, $value] = \explode('=', $name, 2);
		            $that->named[$key] = $value;
		            continue;
		        }

		        if (isset($argv[0]) && $argv[0][0] !== '-') {
		            $that->named[$name] = \array_shift($argv);
		            continue;
		        }

		        $that->named[$name] = true;
		        continue;
		    }

		    if ($arg[0] === '-') {
		        $name = \substr($arg, 1, 1);
		        $value = \substr($arg, 2);

		        if (\str_contains($value, '=')) {
		            break;
		        }

		        if (!empty($value)) {
		            $that->named[$name] = $value;
		            continue;
		        }

		        if (isset($argv[0]) && $argv[0][0] !== '-') {
		            $that->named[$name] = \array_shift($argv);
		            continue;
		        }

		        $that->named[$name] = true;
		        continue;
		    }

		    if ($that->command === '') {
		        $that->command = $arg;
		    } else {
		        $that->positional[] = $arg;
		    }
		}

		return $that;
	}

	/**
	 * Retrieves a specific argument value.
	 *
	 * Depending on the type of key provided, this method returns
	 * either a positional argument (if an integer is provided) or
	 * a named argument (if a string is provided). If the argument
	 * is not found, the default value is returned.
	 *
	 * @param int|string $key
	 * @param mixed      $default
	 * @return mixed
	 */
	public function get(int|string $key, mixed $default = null): mixed
	{
		if (\is_int($key)) {
		    return $this->positional[$key] ?? $default;
		}

		return $this->named[$key] ?? $default;
	}
}

/**
 * A simple dependency injection container that allows binding of
 * factories to service identifiers and resolves them when needed.
 */
class Container
{
	/** @var array<string, object{factory: callable, once: bool}> */
	protected array $bindings = [];

	/**
	 * @template T of object
	 * @var array<class-string<T>, T>
	 */
	protected array $cache = [];

	/**
	 * Bind a service to the container.
	 *
	 * Registers a service with a unique identifier and a factory callable
	 * responsible for creating the service instance.
	 *
	 * @template T of object
	 * @param class-string<T>    $id
	 * @param callable(static):T $factory
	 * @return object{factory: callable, once: bool}
	 */
	public function bind(string $id, callable $factory): object
	{
		return $this->bindings[$id] = new class($factory) {
		    public bool $once = false;

		    public function __construct(
		        public $factory
		    ) {}
		};
	}

	/**
	 * Retrieve a service from the container.
	 *
	 * Resolves and returns a service based on its identifier. If the service
	 * has been previously resolved and marked as a singleton (via the 'once' flag),
	 * the cached instance is returned.
	 *
	 * @template T of object
	 * @param  class-string<T>|string $id
	 * @return ($id is class-string<T> ? T : object)
	 * @throws \RuntimeException
	 */
	public function get(string $id): object
	{
		if (!isset($this->bindings[$id])) {
		    throw new \RuntimeException(\sprintf('No binding for %s exists', $id));
		}

		if (isset($this->cache[$id])) {
		    return $this->cache[$id];
		}

		$binding  = $this->bindings[$id];
		$resolved = \call_user_func($binding->factory, $this);

		if ($binding->once) {
		    $this->cache[$id] = $resolved;
		}

		return $resolved;
	}
}

/**
 * Handles the loading and retrieval of environment variables from a file.
 * The file is parsed line-by-line, skipping comments and empty lines, while
 * converting the configuration into an associative array.
 */
class Environment
{
	/** @var array<string, mixed> */
	public protected(set) array $data = [];

	/**
	 * Loads environment variables from a file.
	 *
	 * Reads a file line-by-line, ignoring lines that are empty or start with a '#'.
	 * Each valid line is parsed into a key-value pair. Values are trimmed, unquoted if necessary,
	 * and typecasted to boolean, null, or numeric values when applicable.
	 *
	 * @param string $file
	 * @return static
	 */
	public function load(string $file): static
	{
		if (!\file_exists($file)) {
		    return $this;
		}

		foreach (\file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
		    if (\trim($line)[0] === '#') {
		        continue;
		    }

		    $parts = \explode('=', $line, 2);

		    if (\count($parts) !== 2) {
		        continue;
		    }

		    $name = \trim($parts[0]);
		    $value = \trim($parts[1]);

		    if (isset($value[0]) && (($value[0] === '"' && \substr($value, -1) === '"') || ($value[0] === "'" && \substr($value, -1) === "'"))) {
		        $value = \substr($value, 1, -1);
		    } else {
		        $lower = \strtolower($value);
		        $value = match (true) {
		            $lower === 'true'  => true,
		            $lower === 'false' => false,
		            $lower === 'null'  => null,
		            \is_numeric($value) => (\str_contains($value, 'e') || \str_contains($value, '.'))
		                ? (float)$value
		                : (int)$value,
		            default            => $value,
		        };
		    }

		    $this->data[$name] = $value;
		}

		return $this;
	}

	/**
	 * Retrieves an environment variable.
	 *
	 * Returns the value of the specified environment variable from the loaded data.
	 * If the variable is not found, the method returns the provided default value.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->data[$key] ?? $default;
	}
}

/**
 * Represents an HTTP exception that can be thrown during request handling.
 */
class HttpException extends \Exception
{
}

/**
 * Encapsulates an HTTP request by extracting data from PHP superglobals.
 * Provides methods to initialize request properties such as method, scheme,
 * host, port, path, parameters, headers, cookies, files, and body content.
 */
class Request
{
	/** @var string */
	public protected(set) string $method {
		set => /*(f*/\strtoupper($value);
	}

	/** @var string */
	public protected(set) string $scheme {
		set => /*(f*/\strtolower($value);
	}

	/** @var ?string */
	public protected(set) ?string $host;

	/** @var ?int */
	public protected(set) ?int $port;

	/** @var string */
	public protected(set) string $path;

	/** @var array<string, mixed> */
	public protected(set) array $parameters;

	/** @var array<string, mixed> */
	public protected(set) array $query;

	/** @var array<string, mixed> */
	public protected(set) array $headers;

	/** @var array<string, mixed> */
	public protected(set) array $cookies;

	/** @var array<string, mixed> */
	public protected(set) array $files;

	/** @var string */
	public protected(set) string $rawInput;

	/** @var array<string, mixed> */
	public protected(set) array $body;

	/**
	 * Initializes and returns a new Request instance using PHP superglobals.
	 *
	 * This method sets the HTTP method, scheme, host, port, path, headers,
	 * cookies, files, and body based on the current request environment.
	 *
	 * @return static
	 */
	public static function init(): static
	{
		$that = new static;

		$that->method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if (\filter_var($_SERVER['HTTPS'] ?? '', FILTER_VALIDATE_BOOLEAN)) {
		    $that->scheme = 'https';
		} else {
		    $that->scheme = 'http';
		}

		$host = null;
		$port = null;

		if (isset($_SERVER['HTTP_HOST'])) {
		    if (\str_contains($_SERVER['HTTP_HOST'], ':')) {
		        [$host, $port] = \explode(':', $_SERVER['HTTP_HOST'], 2);
		        $port = (int) $port;
		    } else {
		        $host = $_SERVER['HTTP_HOST'];
		        $port = $that->scheme === 'https' ? 443 : 80;
		    }
		}

		$that->host = $host ?? $_SERVER['SERVER_NAME'];
		$that->port = $port ?? (int) $_SERVER['SERVER_PORT'];
		$that->path = \parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '/';
		$that->parameters = [];
		$that->query = $_GET ?? [];
		$that->headers = \function_exists('getallheaders') ? (\getallheaders() ?: []) : [];
		$that->cookies = $_COOKIE ?? [];
		$that->files = $_FILES ?? [];

		$that->rawInput = \file_get_contents('php://input') ?: '';

		$contentType = $that->headers['Content-Type'] ?? '';
		$mimeType = \explode(';', $contentType, 2)[0];

		$that->body = match ($mimeType) {
		    'application/x-www-form-urlencoded' => (function (string $input): array {
		        \parse_str($input, $result);
		        return $result;
		    })($that->rawInput),
		    'application/json' => \json_decode($that->rawInput, true),
		    default => [],
		};

		return $that;
	}

	/**
	 * Returns a new Request instance with the specified parameters.
	 *
	 * This method clones the current Request object and updates the
	 * parameters property with the provided array.
	 *
	 * @param array $parameters
	 * @return static
	 */
	public function withParameters(array $parameters): static
	{
		$that = clone $this;
		$that->parameters = $parameters;
		return $that;
	}

	/**
	 * Retrieve a value from the request parameters.
	 *
	 * Checks the custom parameters, then the parsed request body, and finally
	 * the query parameters. If the key is not found, returns the provided default.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get(string $key, mixed $default = null): mixed
	{
		return $this->parameters[$key]
		    ?? $this->body[$key]
		    ?? $this->query[$key]
		    ?? $default;
	}
}

/**
 * Represents an HTTP response that encapsulates the status code, headers,
 * and body. Provides methods to modify the response immutably and send it.
 */
class Response
{
	/** @var int */
	public protected(set) int $status = 200;

	/** @var array<string, mixed> */
	public protected(set) array $headers = [];

	/** @var bool|float|int|string|\Stringable|null */
	public protected(set) bool|float|int|string|\Stringable|null $body = null;

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
		$that->headers = \array_merge($that->headers, $headers);
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
	 * @param bool|float|int|string|\Stringable|null $body
	 * @return static
	 */
	public function withBody(bool|float|int|string|\Stringable|null $body): static
	{
		$that = clone $this;
		$that->body = $body;
		return $that;
	}

	/**
	 * Sends the HTTP response to the client.
	 *
	 * This method sets the HTTP status code, outputs all headers, echoes the response body,
	 * and flushes the output buffer. It returns false if headers have already been sent.
	 *
	 * @return bool
	 */
	public function send(): bool
	{
		if (\headers_sent()) {
		    return false;
		}

		\http_response_code($this->status);

		foreach ($this->headers as $key => $value) {
		    if (\is_array($value)) {
		        foreach ($value as $i => $v) {
		            \header("$key: $v", $i === 0);
		        }
		    } else {
		        \header("$key: $value", true);
		    }
		}

		echo (string) $this->body;
		\flush();
		return true;
	}
}

/**
 * Handles the registration and execution of routes for HTTP requests.
 * Supports both static and dynamic routes with middleware pipelines.
 */
class Router
{
	protected const LEAFNODE = "\x00L";
	protected const WILDCARD = "\x00W";

	/** @var array<string, array{array<callable>, callable}> */
	protected array $staticRoutes = [];

	/** @var array<string, array{array<callable>, callable}> */
	protected array $dynamicRoutes = [];

	/**
	 * Registers a route with the router.
	 *
	 * This method accepts an HTTP method, a route path, a handler callable,
	 * and an optional array of middleware. It processes the path to support
	 * dynamic segments and stores the route in the appropriate routes array.
	 *
	 * @param string         $method
	 * @param string         $path
	 * @param callable       $handle
	 * @param list<callable> $middleware
	 * @return static
	 */
	public function route(string $method, string $path, callable $handle, array $middleware = []): static
	{
		$path = \trim(\preg_replace('/\/+/', '/', $path), '/');

		if (!\str_contains($path, ':')) {
		    $this->staticRoutes[$path][$method] = [$middleware, $handle];
		    return $this;
		}

		$node = &$this->dynamicRoutes;
		$params = [];

		foreach (\explode('/', $path) as $segment) {
		    if (\str_starts_with($segment, ':')) {
		        $node = &$node[static::WILDCARD];
		        $params[] = \substr($segment, 1);
		    } else {
		        $node = &$node[$segment];
		    }
		}

		$node[static::LEAFNODE][$method] = [$params, $middleware, $handle];
		return $this;
	}

	/**
	 * Dispatches the incoming HTTP request and executes the corresponding route.
	 *
	 * This method first checks for a matching static route based on the request path and HTTP method.
	 * If a static match is found, its middleware pipeline and handler are executed.
	 * If no static route is found, it searches for a matching dynamic route using an internal trie structure.
	 *
	 * For dynamic routes, if a match is found, the extracted parameters are merged into the request,
	 * and the associated middleware and handler are executed.
	 *
	 * If no matching route is found, a HttpException with a 404 status code is thrown.
	 * If a matching route is found but does not support the request method, a HttpException with a 405 status code is thrown.
	 *
	 * @param Request $request
	 * @return Response
	 *
	 * @throws HttpException
	 */
	public function dispatch(Request $request): Response
	{
		if (isset($this->staticRoutes[$request->path][$request->method])) {
		    [$middleware, $handle] = $this->staticRoutes[$request->path][$request->method];
		    return $this->call($request, $middleware, $handle);
		}

		$result = $this->search($this->dynamicRoutes, \explode('/', $request->path));

		if ($result === null) {
		    throw new HttpException("Route not found", 404);
		}

		[$values, $methods] = $result;

		if (!isset($methods[$request->method])) {
		    throw new HttpException("Method not allowed", 405);
		}

		[$params, $middleware, $handle] = $methods[$request->method];

		$req = $request->withParameters(\array_combine($params, $values));
		return $this->call($req, $middleware, $handle);
	}

	/**
	 * Recursively searches the route trie for a matching route.
	 *
	 * Traverses the trie using the provided path segments to determine if a route exists.
	 * If a segment matches a literal or wildcard, the function recursively proceeds.
	 * When all segments have been processed, if a leaf node is found, it returns an array
	 * containing the dynamic parameters extracted from the path and the associated methods mapping.
	 *
	 * @param array $trie
	 * @param array $segments
	 * @param array $params
	 * @return array|null
	 */
	protected function search(array $trie, array $segments, array $params = []): ?array
	{
		if (empty($segments)) {
		    if (isset($trie[static::LEAFNODE])) {
		        return [$params, $trie[static::LEAFNODE]];
		    }
		    return null;
		}

		$segment = \array_shift($segments);

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
	 * This method constructs a pipeline where each middleware wraps around the handler.
	 * The request and a new Response instance are passed through the pipeline.
	 * The final Response is then returned.
	 *
	 * @param Request  $request
	 * @param array    $middleware
	 * @param callable $handle
	 * @return Response
	 */
	protected function call(Request $request, array $middleware, callable $handle): Response
	{
		$pipeline = $handle;

		foreach (\array_reverse($middleware) as $m) {
		    $pipeline = fn($req, $res) => \call_user_func($m, $req, $res, $pipeline);
		}

		$response = new Response();
		$result = \call_user_func($pipeline, $request, $response);

		if ($result instanceof Response) {
		    return $result;
		}

		return $response;
	}
}

/**
 * A custom session handler using APCu for storing session data.
 * Implements both SessionHandlerInterface and SessionUpdateTimestampHandlerInterface.
 */
class SessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
	/**
	 * SessionHandler constructor.
	 *
	 * @param int $ttl
	 */
	public function __construct(
		protected int $ttl = 3600,
	) {
	}

	/**
	 * Closes the session.
	 *
	 * This method is invoked when the session is closed.
	 *
	 * @return bool
	 */
	public function close(): bool
	{
		return true;
	}

	/**
	 * Destroys a session.
	 *
	 * Deletes the session data from APCu.
	 *
	 * @param string $id
	 * @return bool
	 */
	public function destroy(string $id): bool
	{
		return \apcu_delete($this->key($id));
	}

	/**
	 * Performs garbage collection for sessions.
	 *
	 * This method is not utilized with APCu, as APCu handles expiration internally.
	 *
	 * @param int $max_lifetime
	 * @return int
	 */
	public function gc(int $max_lifetime): int
	{
		return 0;
	}

	/**
	 * Opens a session.
	 *
	 * Called when a session is started. No specific action is needed for APCu.
	 *
	 * @param string $path
	 * @param string $name
	 * @return bool
	 */
	public function open(string $path, string $name): bool
	{
		return true;
	}

	/**
	 * Reads session data.
	 *
	 * Retrieves the session data from APCu.
	 *
	 * @param string $id
	 * @return string
	 */
	public function read(string $id): string
	{
		return \apcu_fetch($this->key($id)) ?: '';
	}

	/**
	 * Writes session data.
	 *
	 * Stores the session data in APCu with the configured TTL.
	 *
	 * @param string $id
	 * @param string $data
	 * @return bool
	 */
	public function write(string $id, string $data): bool
	{
		return \apcu_store($this->key($id), $data, $this->ttl);
	}

	/**
	 * Validates a session ID.
	 *
	 * Checks if session data exists in APCu for the given session ID.
	 *
	 * @param string $id
	 * @return bool
	 */
	public function validateId(string $id): bool
	{
		return \apcu_exists($this->key($id));
	}

	/**
	 * Updates the session's timestamp.
	 *
	 * Refreshes the session data in APCu by storing it again with the current TTL.
	 *
	 * @param string $id
	 * @param string $data
	 * @return bool
	 */
	public function updateTimestamp(string $id, string $data): bool
	{
		return \apcu_store($this->key($id), $data, $this->ttl);
	}

	/**
	 * Generates the APCu key for a session.
	 *
	 * Prepends a prefix to the session ID to ensure a unique key.
	 *
	 * @param string $id
	 * @return string
	 */
	protected function key(string $id): string
	{
		return "session_" . $id;
	}
}

/**
 * This function fetches an environment variable from the Environment instance.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
	return \app(Environment::class)->get($key, $default);
}

/**
 * If no identifier is provided, returns the container instance.
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return ($id is class-string<T> ? T : object)
 */
function app(?string $id = null): object
{
	return $id
	    ? Application::$container->get($id)
	    : Application::$container;
}

/**
 * This function binds a service to the container using the specified identifier and factory.
 *
 * @param string $id
 * @param callable $factory
 * @return object
 */
function bind(string $id, callable $factory): object
{
	return \app()->bind($id, $factory);
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
	if (Application::$isWeb) {
	    return;
	}

	$argv = \app(Argument::class);

	if ($argv->command !== $name) {
	    return;
	}

	$result = $handle($argv);

	exit(\is_int($result) ? $result : 0);
}

/**
 * Fetches a value from the current Request instance using the specified key.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function request(string $key, mixed $default = null): mixed
{
	return \app(Request::class)->get($key, $default);
}

/**
 * Registers a route for the given HTTP method and path with an associated handler and optional middleware.
 * This function is only applicable in a web environment.
 *
 * @param string         $method
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function route(string $method, string $path, callable $handle, array $middleware = []): void
{
	if (!Application::$isWeb) {
	    return;
	}

	\app(Router::class)->route($method, $path, $handle, $middleware);
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
	if (\session_status() !== \PHP_SESSION_ACTIVE) {
	    return \null;
	}

	if ($value !== \null) {
	    return $_SESSION['_flash'][$key] = $value;
	}

	$val = $_SESSION['_flash'][$key] ?? \null;
	unset($_SESSION['_flash'][$key]);
	return $val;
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
	if (\session_status() !== \PHP_SESSION_ACTIVE) {
	    return \null;
	}

	if ($value !== \null) {
	    return $_SESSION[$key] = $value;
	}

	return $_SESSION[$key] ?? \null;
}

/**
 * This function attempts to resolve the template as a file based on the Essentio\Core\Application's base path.
 * If the resolved path is a file, it extracts the provided data, includes the file, and returns the output.
 * Otherwise, if the template string contains placeholder patterns (using curly braces),
 * it processes the string using regex callbacks to replace placeholders with data values.
 *
 * @param string $template
 * @param array  $data
 * @return string
 */
function render(string $template, array $data = []): string
{
	if ($path = Application::fromBase($template)) {
	    \extract($data);
	    \ob_start();
	    include $path;
	    return \ob_get_clean();
	}

	if (!\str_contains($template, '{{') || !\str_contains($template, '}}')) {
	    return '';
	}

	return \preg_replace_callback_array([
	    '/\{\{\{\s*(.*?)\s*\}\}\}/' => fn($matches) => $data[$matches[1]] ?? '',
	    '/\{\{\s*(.*?)\s*\}\}/' => fn($matches) => isset($data[$matches[1]])
	        ? \htmlentities($data[$matches[1]])
	        : '',
	], $template);
}

/**
 * Returns a Response instance configured to redirect to the specified URI with the given status code.
 *
 * @param string $uri
 * @param int    $status
 * @return Essentio\Core\Response
 */
function redirect(string $uri, int $status = 302): Response
{
	return (new Response)
	    ->withStatus($status)
	    ->withHeaders(['Location' => $uri]);
}

/**
 * Returns a Response instance configured to send JSON data with the specified status code.
 *
 * @param mixed $data
 * @param int   $status
 * @return Essentio\Core\Response
 */
function json(mixed $data, int $status = 200): Response
{
	return (new Response)
	    ->withStatus($status)
	    ->withHeaders(['Content-Type' => 'Essentio\Core\Application/json'])
	    ->withBody(\json_encode($data));
}

/**
 * Returns a Response instance configured to send plain text with the specified status code.
 *
 * @param string $text
 * @param int    $status
 * @return Essentio\Core\Response
 */
function text(string $text, int $status = 200): Response
{
	return (new Response)
	    ->withStatus($status)
	    ->withHeaders(['Content-Type' => 'text/plain'])
	    ->withBody($text);
}

/**
 * Returns a Response instance configured to render an HTML view using the provided template and data.
 *
 * @param string $template
 * @param array  $data
 * @param int    $status
 * @return Essentio\Core\Response
 */
function view(string $template, array $data = [], int $status = 200): Response
{
	return (new Response)
	    ->withStatus($status)
	    ->withHeaders(['Content-Type' => 'text/html'])
	    ->withBody(\render($template, $data));
}

/**
 * This function logs a message using PHP's error_log function.
 *
 * @param string $format
 * @param mixed ...$values
 * @return void
 */
function log_cli(string $format, ...$values): void
{
	\error_log(\sprintf($format, ...$values));
}

/**
 * Logs a message at a given log level to a file specified in the configuration.
 *
 * @param string $level
 * @param string $message
 * @return void
 */
function logger(string $level, string $message): void
{
	$file = \config(\sprintf('log.%s', \strtolower($level)), 'app.log');
	$file = Application::fromBase(\dirname($file)) . '/' . \basename($file);

	if (!\is_file($file)) {
	    \touch($file);
	}

	$msg = \sprintf("[%s] [%s]: %s\n", \date('Y-m-d H:i:s'), \strtoupper($level), $message);

	\file_put_contents($file, $msg, \FILE_APPEND);
}

/**
 * In CLI mode, the data is dumped using var_dump.
 * In a web environment, the output is wrapped in <pre> tags.
 *
 * @param mixed ...$data
 * @return void
 */
function dump(...$data): void
{
	if (!Application::$isWeb) {
	    \var_dump(...$data);
	    return;
	}

	echo '<pre>';
	\var_dump(...$data);
	echo '</pre>';
}

/**
 * Evaluates the provided condition, and if it is true, throws the specified exception.
 *
 * @param bool $condition
 * @param \Throwable $e
 * @return void
 * @throws \Throwable
 */
function throw_if(bool $condition, \Throwable $e): void
{
	if ($condition) {
	    throw $e;
	}
}

/**
 * This function attempts to execute the provided callable. If any exception or error is thrown,
 * it logs the error message using the error log mechanism and returns the default value.
 *
 * @param callable $callback
 * @param mixed    $default
 * @return mixed
 */
function safe(callable $callback, mixed $default = null): mixed
{
	try {
	    return $callback();
	} catch (\Throwable $e) {
	    \logger('error', $e->getMessage());
	    return $default;
	}
}

/**
 * This function allows you to perform an operation on the value and then
 * return the original value.
 *
 * @param mixed    $value
 * @param callable $callback
 * @return mixed
 */
function tap(mixed $value, callable $callback): mixed
{
	$callback($value);
	return $value;
}

/**
 * If the value is callable, it executes the callback and returns its result.
 * Otherwise, it returns the value as is.
 *
 * @param mixed $value
 * @return mixed
 */
function value(mixed $value): mixed
{
	if (\is_callable($value)) {
	    return $value();
	}

	return $value;
}

/**
 * If the condition is false, the function returns null.
 * If the condition is true and the callback is callable, it executes the callback and returns its result;
 * otherwise, it returns the provided value directly.
 *
 * @param bool  $condition
 * @param mixed $callback
 * @return mixed
 */
function when(bool $condition, mixed $callback): mixed
{
	if (!$condition) {
	    return \null;
	}

	if (\is_callable($callback)) {
	    return $callback();
	}

	return $callback;
}
