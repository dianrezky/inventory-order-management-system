<?php

namespace App\Repository\MySQL;

use App\Core\Database;


class QueryBuilder
{
    private $db;

    // Non-identifier characters are replaced with "_" when deriving a safe PDO
    // placeholder name from a column. \W === [^A-Za-z0-9_] in non-unicode PCRE.
    private const PLACEHOLDER_SANITIZER = '/\W/';

    // SQL glue for combining WHERE conditions.
    private const SQL_AND = ' AND ';

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    // Returns every matching row as a plain assoc array; repos map each row
    // through their own Entity::fromArray().
    public function findAll(
        $table,
        $alias,
        $columns,
        $joins = [],
        $searchColumns = [],
        $search = null,
        $filters = [],
        $orderBy = null,
        $limit = 0,
        $offset = 0,
        $likeFilters = [],
        $notEqualsFilters = [],
        $groupBy = null,
        $having = null,
        $operatorFilters = [],
        $inFilters = []
    ) {
        [$sql, $params] = $this->buildSelect($table, $alias, $columns, $joins, $searchColumns, $search, $filters, $likeFilters, $notEqualsFilters, $operatorFilters, $inFilters);

        if ($groupBy !== null) {
            $sql .= ' GROUP BY ' . $groupBy;
        }

        if ($having !== null) {
            // Raw HAVING fragment with placeholders — caller must pass matching
            // entries in $havingParams. Each placeholder :foo MUST have a key in
            // $havingParams; this is to keep QueryBuilder a SELECT-assembly
            // helper, not a full HAVING DSL.
            $sql .= ' HAVING ' . $having['sql'];
            foreach ($having['params'] ?? [] as $key => $value) {
                $params[$key] = $value;
            }
        }

        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . $orderBy;
        }

        if ($limit > 0) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    // Same WHERE/JOIN rules as findAll(), but returns exactly one row (or
    // null) — for findById()-style single-record lookups. $forUpdate appends
    // FOR UPDATE for callers that must lock the row inside a transaction
    // (e.g. stock adjustment — ARCH-02).
    public function findOne($table, $alias, $columns, $joins = [], $filters = [], $likeFilters = [], $forUpdate = false, $notEqualsFilters = [], $operatorFilters = [], $inFilters = [])
    {
        [$sql, $params] = $this->buildSelect($table, $alias, $columns, $joins, [], null, $filters, $likeFilters, $notEqualsFilters, $operatorFilters, $inFilters);

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function countAll($table, $alias, $joins = [], $searchColumns = [], $search = null, $filters = [], $likeFilters = [], $notEqualsFilters = [], $operatorFilters = [], $inFilters = [], $groupBy = null, $having = null)
    {
        // A grouped count selects the base table's columns in the inner query:
        // MySQL only lets HAVING reference non-aggregated columns (p.reorder_point,
        // p.is_active) that are in the select list, same as findAll()'s "p.*".
        $innerSelect = 'COUNT(*)';
        if ($groupBy !== null) {
            $innerSelect = ($alias !== null ? $alias : $table) . '.*';
        }
        [$sql, $params] = $this->buildSelect($table, $alias, $innerSelect, $joins, $searchColumns, $search, $filters, $likeFilters, $notEqualsFilters, $operatorFilters, $inFilters);

        // HAVING must sit INSIDE the grouped subquery, next to its GROUP BY: it
        // references the inner aliases (p, ps) and aggregates. Appended to the outer
        // "SELECT COUNT(*) FROM (…) AS cnt" those aliases don't exist, the query
        // errored, and callers silently got a count of 0 ("Showing 1 to 0 of 0").
        $havingSql = '';
        if ($having !== null) {
            $havingSql = ' HAVING ' . $having['sql'];
            foreach ($having['params'] ?? [] as $key => $value) {
                $params[$key] = $value;
            }
        }

        if ($groupBy !== null) {
            $sql = "SELECT COUNT(*) FROM ({$sql} GROUP BY {$groupBy}{$havingSql}) AS cnt";
        } else {
            $sql .= $havingSql;
        }

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    // Same WHERE/JOIN rules again, but for a single-value aggregate
    // expression (e.g. 'COALESCE(SUM(qty), 0)') rather than a row or a count.
    public function scalar($table, $alias, $expression, $joins = [], $filters = [], $likeFilters = [], $notEqualsFilters = [], $operatorFilters = [], $inFilters = [])
    {
        [$sql, $params] = $this->buildSelect($table, $alias, $expression, $joins, [], null, $filters, $likeFilters, $notEqualsFilters, $operatorFilters, $inFilters);

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }

    // Builds and executes a parameterized INSERT for $columns (column => value),
    // returning the new row's auto-increment id — the shared implementation
    // behind every repository's create()/insert() method (CLAUDE.md §4: no
    // raw SQL in Service, no query concatenation with user input).
    public function insert($table, array $columns)
    {
        $columnNames = array_keys($columns);
        $placeholders = [];
        $params = [];

        foreach ($columnNames as $column) {
            $placeholder = 'ins_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $placeholders[] = ":{$placeholder}";
            $params[$placeholder] = $columns[$column];
        }

        $sql = "INSERT INTO {$table} (" . implode(', ', $columnNames) . ') VALUES (' . implode(', ', $placeholders) . ')';

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    // Builds and executes a parameterized UPDATE: SET $columns WHERE $filters
    // (both column => value, ANDed together). Returns the affected row count.
    // For a SET clause that isn't a plain value assignment (e.g. an atomic
    // increment), use incrementColumn() below instead — don't pass a raw SQL
    // fragment as a value here, it will be bound as a literal string.
    public function update($table, array $columns, array $filters)
    {
        $sets = [];
        $params = [];

        foreach ($columns as $column => $value) {
            $placeholder = 'set_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $sets[] = "{$column} = :{$placeholder}";
            $params[$placeholder] = $value;
        }

        $wheres = [];
        foreach ($filters as $column => $value) {
            $placeholder = 'where_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $wheres[] = "{$column} = :{$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE ' . implode(self::SQL_AND, $wheres);

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    // Builds and executes a parameterized DELETE: DELETE FROM $table WHERE
    // $filters (column => value, ANDed together). Returns the affected row
    // count. Used only where the domain genuinely wants a hard delete (e.g.
    // CategoryService, after confirming zero dependent rows) — every other
    // repository in this codebase models "delete" as a soft is_active flip
    // via update(), which stays the default; this is the deliberate
    // exception, not a general-purpose replacement for it.
    public function delete($table, array $filters)
    {
        if (empty($filters)) {
            // Guard against an unfiltered DELETE FROM $table wiping the whole
            // table because a caller forgot to pass a WHERE condition.
            throw new \InvalidArgumentException('delete() requires at least one filter');
        }

        $wheres = [];
        $params = [];

        foreach ($filters as $column => $value) {
            $placeholder = 'where_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $wheres[] = "{$column} = :{$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(self::SQL_AND, $wheres);

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    // Atomic "column = column + delta" update (e.g. stock qty adjustments,
    // ARCH-02: two concurrent goods issues must not oversell) — deliberately
    // separate from update() because this SET clause is a DB-side expression,
    // not a bound value, and must stay that way to remain race-free.
    public function incrementColumn($table, $column, $delta, array $filters)
    {
        $safeColumn = preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
        if ($safeColumn !== $column) {
            throw new \InvalidArgumentException("Unsupported column: $column");
        }

        $params = ['delta' => $delta];

        $wheres = [];
        foreach ($filters as $filterColumn => $value) {
            $placeholder = 'where_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $filterColumn);
            $wheres[] = "{$filterColumn} = :{$placeholder}";
            $params[$placeholder] = $value;
        }

        $sql = "UPDATE {$table} SET {$column} = {$column} + :delta WHERE " . implode(self::SQL_AND, $wheres);

        $stmt = $this->db->pdo()->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    private function buildSelect($table, $alias, $columns, $joins, $searchColumns, $search, $filters, $likeFilters = [], $notEqualsFilters = [], $operatorFilters = [], $inFilters = [])
    {
        $from = $alias !== null ? "{$table} {$alias}" : $table;
        $sql = "SELECT {$columns} FROM {$from}";
        $params = [];

        foreach ($joins as $join) {
            $type = $join['type'] ?? 'LEFT';
            $sql .= " {$type} JOIN {$join['table']} {$join['alias']} ON {$join['on']}";
        }

        $conditions = [];

        // Each LIKE gets its own named placeholder — MySQL's native prepared
        // statements reject the same named placeholder used more than once.
        if ($search !== null && $search !== '' && $searchColumns !== []) {
            $likes = [];
            foreach ($searchColumns as $i => $col) {
                $placeholder = 'search' . $i;
                $likes[] = "{$col} LIKE :{$placeholder}";
                $params[$placeholder] = '%' . $search . '%';
            }
            $conditions[] = '(' . implode(' OR ', $likes) . ')';
        }

        // Independent LIKE conditions (each its own column/value, ANDed
        // together) — distinct from $search/$searchColumns above, which OR
        // one shared term across several columns.
        foreach ($likeFilters as $column => $value) {
            $placeholder = 'like_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $conditions[] = "{$column} LIKE :{$placeholder}";
            $params[$placeholder] = '%' . $value . '%';
        }

        foreach ($filters as $column => $value) {
            $placeholder = 'filter_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $conditions[] = "{$column} = :{$placeholder}";
            $params[$placeholder] = $value;
        }

        // Not-equals filters (e.g. exclude-id check). Same naming pattern as
        // $filters but with the != operator; useful for "duplicate except me"
        // checks during edit.
        foreach ($notEqualsFilters as $column => $value) {
            $placeholder = 'neq_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $conditions[] = "{$column} != :{$placeholder}";
            $params[$placeholder] = $value;
        }

        // Operator filters: list of ['column', op, value] tuples for !=, <, <=,
        // >, >=, etc. — separate from $filters/$notEqualsFilters to keep the
        // simpler equality semantics readable. Op whitelist is enforced below
        // to avoid accidental SQL injection through a malformed op string.
        static $allowedOps = ['=', '!=', '<>', '<', '<=', '>', '>='];
        foreach ($operatorFilters as $i => $opFilter) {
            [$column, $op, $value] = $opFilter;
            if (!in_array($op, $allowedOps, true)) {
                throw new \InvalidArgumentException("Unsupported operator: $op");
            }
            $placeholder = 'op_' . $i . '_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column);
            $conditions[] = "{$column} {$op} :{$placeholder}";
            $params[$placeholder] = $value;
        }

        // IN filters: column => [val1, val2, ...] — generates "column IN (:p0, :p1, ...)"
        foreach ($inFilters as $column => $values) {
            if (!is_array($values) || count($values) === 0) { continue; }
            $placeholders = [];
            foreach ($values as $i => $v) {
                $ph = 'in_' . preg_replace(self::PLACEHOLDER_SANITIZER, '_', $column) . '_' . $i;
                $placeholders[] = ':' . $ph;
                $params[$ph] = $v;
            }
            $conditions[] = "{$column} IN (" . implode(', ', $placeholders) . ")";
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(self::SQL_AND, $conditions);
        }

        return [$sql, $params];
    }
}
