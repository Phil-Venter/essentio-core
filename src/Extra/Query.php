<?php

namespace Essentio\Core\Extra;

use InvalidArgumentException;
use PDO;

// Aliases not working properly
class Query implements \Stringable
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

    public function __construct(protected PDO $pdo)
    {
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
        string $type = ""
    ): static {
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

    public function whereRaw(string $sql, array $data = [], string $boolean = "AND"): static
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
        string $boolean = "AND"
    ): static {
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

    public function havingRaw(string $sql, array $data = [], string $boolean = "AND"): static
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
        string $boolean = "AND"
    ): static {
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

    public function orderBy(string|array $column, string $direction = "ASC"): static
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

    public function union(callable $callback, string $type = ""): static
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
        string $boolean = "AND"
    ): array {
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
