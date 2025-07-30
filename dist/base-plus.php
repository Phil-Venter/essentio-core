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
     * Autoloading without composer required.
     */
    public static function autoload(array $registry): void
    {
        foreach ($registry as $prefix => $path) {
            // Immediate inclusion for individual files
            if (is_file($path)) {
                if (is_readable($path)) {
                    require_once $path;
                }

                continue;
            }

            // Normalize prefix and base directory
            $prefix = rtrim($prefix, '\\') . '\\';
            $length = strlen($prefix);
            $path = rtrim((string) $path, '/') . '/';

            // Register class autoloader
            spl_autoload_register(function ($class) use ($prefix, $length, $path): void {
                if (strncmp($prefix, $class, $length) !== 0) {
                    return;
                }

                $file = $path . str_replace('\\', '/', substr($class, $length)) . '.php';

                if (is_file($file) && is_readable($file)) {
                    require_once $file;
                }
            });
        }
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
