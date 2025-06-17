<?php

namespace Essentio\Core\Extra;

use Closure;
use DateTimeInterface;
use Essentio\Core\Application;
use InvalidArgumentException;
use Iterator;
use PDO;
use RuntimeException;
use Stringable;

class Query
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

    public static function create(?PDO $pdo = null): static
    {
        return new static($pdo ?? Application::$container->resolve(PDO::class));
    }

    public function or(): static
    {
        $this->bool = "OR";
        return $this;
    }

    protected function consumeBool(): string
    {
        $bool = $this->bool;
        $this->bool = "AND";
        return " $bool ";
    }

    public function select(array|string ...$columns): static
    {
        $columns = array_values((array) $columns);
        $this->columns = array_merge($this->columns, $columns);
        return $this;
    }

    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }

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

    public function whereRaw(string $statement, array $data = []): static
    {
        $this->where[] = "{$this->consumeBool()} {$statement}";
        $this->whereParams = array_merge($this->whereParams, $data);
        return $this;
    }

    public function groupBy(array|string ...$groupBys): static
    {
        $groupBys = array_values((array) $groupBys);
        $this->groupBys = array_merge($this->groupBys, $groupBys);
        return $this;
    }

    public function havingRaw(string $statement, array $data = []): static
    {
        $this->having[] = "{$this->consumeBool()} {$statement}";
        $this->havingParams = array_merge($this->havingParams, $data);
        return $this;
    }

    public function orderBy(string $column, string $direction = "ASC"): static
    {
        $this->orderBys[] = "{$column} {$direction}";
        return $this;
    }

    public function limit(int $limit, ?int $offset = null): static
    {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

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

    protected function clean(array $statements): string
    {
        return preg_replace("/^\s*(AND|OR)\s*/", "", implode(" ", $statements));
    }

    protected function getParams(): array
    {
        return array_merge($this->whereParams, $this->havingParams);
    }

    public function get(): Iterator
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

    public function first(): array
    {
        $this->limit = 1;

        foreach ($this->get() as $row) {
            return $row;
        }

        return [];
    }

    public function insert(array $data): ?int
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
