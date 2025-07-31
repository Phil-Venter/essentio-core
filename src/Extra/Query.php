<?php

declare(strict_types=1);

namespace Essentio\Extra;

use Closure;
use DateTimeInterface;
use Essentio\FrameworkException;
use Generator;
use PDO;
use Stringable;

/**
 * @api
 */
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
