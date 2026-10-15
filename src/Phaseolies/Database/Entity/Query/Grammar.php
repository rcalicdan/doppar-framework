<?php

namespace Phaseolies\Database\Entity\Query;

trait Grammar
{
    /**
     * Matches a plain column name or a table-qualified column name.
     */
    private const string VALID_IDENTIFIER_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/';

    /**
     * Ensure a value used as a column identifier is safe.
     *
     * @throws \InvalidArgumentException
     */
    protected function assertValidIdentifier(string $identifier): void
    {
        if (!preg_match(self::VALID_IDENTIFIER_PATTERN, $identifier)) {
            throw new \InvalidArgumentException(
                "Invalid column identifier \"{$identifier}\". " .
                "Use whereRaw()/orderByRaw()/groupByRaw() to build conditions from raw SQL expressions."
            );
        }
    }

    /**
     * Determine if a value is a callback (closure, invokable or array callable).
     */
    protected function isCallback(mixed $value): bool
    {
        return !is_string($value) && is_callable($value);
    }

    /**
     * Get the current driver.
     */
    public function getDriver(): string
    {
        return (string) $this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Get the random function as per driver.
     */
    public function rand(): string
    {
        return match ($this->getDriver()) {
            'sqlite', 'pgsql' => 'RANDOM()',
            default           => 'RAND()',
        };
    }

    /**
     * Get the SQL query to retrieve table column information.
     */
    public function getTableColumnsSql(string $tableName): string
    {
        return match ($this->getDriver()) {
            'sqlite' => "PRAGMA table_info({$tableName})",
            'pgsql' => "SELECT column_name FROM information_schema.columns WHERE table_name = '{$tableName}'",
            default => "DESCRIBE {$tableName}",
        };
    }

    /**
     * Process the result to extract column names based on driver.
     *
     * @param array<int, array<string, mixed>> $result
     * @return array<int, string>
     */
    public function processTableColumnsResult(array $result): array
    {
        return match ($this->getDriver()) {
            'sqlite' => array_column($result, 'name'),
            'pgsql' => array_column($result, 'column_name'),
            'mysql' => $this->processMysqlTableColumns($result),
            default => $this->processMysqlTableColumns($result),
        };
    }

    /**
     * Process MySQL DESCRIBE result to extract column names.
     *
     * @param array<int, array<string, mixed>> $result
     * @return array<int, string>
     */
    protected function processMysqlTableColumns(array $result): array
    {
        $columns = [];
        foreach ($result as $row) {
            if (isset($row['Field']) && is_string($row['Field'])) {
                $columns[] = $row['Field'];
            } elseif (isset($row['field']) && is_string($row['field'])) {
                $columns[] = $row['field'];
            } elseif (!empty($row) && is_array($row)) {
                $firstValue = reset($row);
                if (is_string($firstValue)) {
                    $columns[] = $firstValue;
                }
            }
        }

        return $columns;
    }

    /**
     * Get the SQL to list tables for the current database driver.
     */
    public function getTablesSql(): string
    {
        return match ($this->getDriver()) {
            'mysql' => "SHOW TABLES",
            'pgsql' => "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'",
            'sqlite' => "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'",
            default => "SHOW TABLES"
        };
    }

    /**
     * Get JSON contains expression for the current database driver.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    public function jsonContains(string $column, string $path, mixed $value): array
    {
        return match ($this->getDriver()) {
            'mysql' => $this->mysqlJsonContains($column, $path, $value),
            'pgsql' => $this->pgsqlJsonContains($column, $path, $value),
            'sqlite' => $this->sqliteJsonContains($column, $path, $value),
            default => $this->mysqlJsonContains($column, $path, $value),
        };
    }

    /**
     * MySQL JSON_CONTAINS implementation.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function mysqlJsonContains(string $column, string $path, mixed $value): array
    {
        $jsonValue = is_bool($value)
            ? ($value ? 'true' : 'false')
            : json_encode($value);

        return [
            "JSON_CONTAINS(`{$column}`, CAST(? AS JSON), '{$path}')",
            [$jsonValue]
        ];
    }

    /**
     * PostgreSQL JSON contains implementation.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function pgsqlJsonContains(string $column, string $path, mixed $value): array
    {
        $cleanPath = str_replace('$.', '', $path);
        $pathParts = explode('.', $cleanPath);

        $jsonAccess = $column . "::jsonb";

        foreach ($pathParts as $part) {
            $jsonAccess .= " -> '{$part}'";
        }

        if (is_bool($value)) {
            $finalAccess = "({$jsonAccess})::boolean = ?";
            return [$finalAccess, [$value]];
        } elseif (is_string($value)) {
            $finalAccess = str_replace(' -> ', ' ->> ', $jsonAccess) . " = ?";
            return [$finalAccess, [$value]];
        } else {
            $finalAccess = "({$jsonAccess})::text = ?";
            return [$finalAccess, [json_encode($value)]];
        }
    }

    /**
     * SQLite JSON contains implementation.
     * 
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function sqliteJsonContains(string $column, string $path, mixed $value): array
    {
        if (is_bool($value)) {
            return [
                "json_extract({$column}, ?) = ?",
                [$path, $value ? 1 : 0]
            ];
        } elseif (is_string($value)) {
            return [
                "json_extract({$column}, ?) = ?",
                [$path, $value]
            ];
        } else {
            return [
                "json_extract({$column}, ?) = ?",
                [$path, $value]
            ];
        }
    }

    /**
     * Extract the last segment from JSON path.
     */
    protected function getLastPathSegment(string $path): string
    {
        $parts = explode('.', $path);

        return (string) end($parts);
    }

    /**
     * Get the month extraction function for the current database driver
     */
    public function month(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql' => "MONTH({$column})",
            'pgsql' => "EXTRACT(MONTH FROM {$column})",
            'sqlite' => "CAST(strftime('%m', {$column}) AS INTEGER)",
            default => "MONTH({$column})",
        };
    }

    /**
     * Get the year extraction function for the current database driver
     */
    public function year(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql' => "YEAR({$column})",
            'pgsql' => "EXTRACT(YEAR FROM {$column})",
            'sqlite' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
            default => "YEAR({$column})",
        };
    }

    /**
     * Get the day extraction function for the current database driver
     */
    public function day(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql' => "DAY({$column})",
            'pgsql' => "EXTRACT(DAY FROM {$column})",
            'sqlite' => "CAST(strftime('%d', {$column}) AS INTEGER)",
            default => "DAY({$column})",
        };
    }

    /**
     * Get the date part extraction function for the current database driver
     */
    public function datePart(string $column, string $part): string
    {
        return match ($this->getDriver()) {
            'mysql' => match ($part) {
                'month' => "MONTH({$column})",
                'year' => "YEAR({$column})",
                'day' => "DAY({$column})",
                'quarter' => "QUARTER({$column})",
                'week' => "WEEK({$column})",
                'dayofweek' => "DAYOFWEEK({$column})",
                default => "{$part}({$column})"
            },
            'pgsql' => match ($part) {
                'month', 'year', 'day', 'quarter', 'week', 'dow' => "EXTRACT({$part} FROM {$column})",
                'dayofweek' => "EXTRACT(DOW FROM {$column})",
                default => "EXTRACT({$part} FROM {$column})"
            },
            'sqlite' => match ($part) {
                'month' => "CAST(strftime('%m', {$column}) AS INTEGER)",
                'year' => "CAST(strftime('%Y', {$column}) AS INTEGER)",
                'day' => "CAST(strftime('%d', {$column}) AS INTEGER)",
                'quarter' => "((CAST(strftime('%m', {$column}) AS INTEGER) - 1) / 3 + 1)",
                'week' => "CAST(strftime('%W', {$column}) AS INTEGER)",
                'dayofweek' => "CAST(strftime('%w', {$column}) AS INTEGER)",
                default => "strftime('%{$part}', {$column})"
            },
            default => "{$part}({$column})"
        };
    }

    /**
     * Get the time extraction function for the current database driver
     */
    public function time(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql' => "TIME({$column})",
            'pgsql' => "{$column}::time",
            'sqlite' => "time({$column})",
            default => "TIME({$column})",
        };
    }

    /**
     * Get the hour extraction function for the current database driver
     */
    public function hour(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql' => "HOUR({$column})",
            'pgsql' => "EXTRACT(HOUR FROM {$column})",
            'sqlite' => "CAST(strftime('%H', {$column}) AS INTEGER)",
            default => "HOUR({$column})",
        };
    }

    /**
     * Get the minute extraction function for the current database driver
     */
    public function minute(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql' => "MINUTE({$column})",
            'pgsql' => "EXTRACT(MINUTE FROM {$column})",
            'sqlite' => "CAST(strftime('%M', {$column}) AS INTEGER)",
            default => "MINUTE({$column})",
        };
    }

    /**
     * Get the second extraction function for the current database driver
     */
    public function second(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql' => "SECOND({$column})",
            'pgsql' => "EXTRACT(SECOND FROM {$column})",
            'sqlite' => "CAST(strftime('%S', {$column}) AS INTEGER)",
            default => "SECOND({$column})",
        };
    }

    /**
     * Get the time part extraction function for the current database driver
     */
    public function timePart(string $column, string $part): string
    {
        return match ($this->getDriver()) {
            'mysql' => match ($part) {
                'hour' => "HOUR({$column})",
                'minute' => "MINUTE({$column})",
                'second' => "SECOND({$column})",
                'time' => "TIME({$column})",
                default => "{$part}({$column})"
            },
            'pgsql' => match ($part) {
                'hour', 'minute', 'second' => "EXTRACT({$part} FROM {$column})",
                'time' => "{$column}::time",
                default => "EXTRACT({$part} FROM {$column})"
            },
            'sqlite' => match ($part) {
                'hour' => "CAST(strftime('%H', {$column}) AS INTEGER)",
                'minute' => "CAST(strftime('%M', {$column}) AS INTEGER)",
                'second' => "CAST(strftime('%S', {$column}) AS INTEGER)",
                'time' => "time({$column})",
                default => "strftime('%{$part}', {$column})"
            },
            default => "{$part}({$column})"
        };
    }

    /**
     * Get the date extraction function for the current database driver
     */
    public function date(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql', 'pgsql' => "DATE({$column})",
            'sqlite' => "date({$column})",
            default => "DATE({$column})",
        };
    }

    /**
     * Get the standard deviation function for the current database driver
     */
    public function getStandardDeviation(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql', 'pgsql' => "STDDEV({$column})",
            'sqlite' => "SQRT(AVG({$column} * {$column}) - AVG({$column}) * AVG({$column}))",
            default => "SQRT(AVG({$column} * {$column}) - AVG({$column}) * AVG({$column}))",
        };
    }

    /**
     * Should standard deviation be computed in PHP instead of SQL
     * Only SQLite does not support SQRT function ATM
     */
    public function shouldComputeStdDevInPhp(): bool
    {
        return $this->getDriver() === 'sqlite';
    }

    /**
     * Get the group concatenation expression for the current database driver
     */
    protected function getGroupConcatExpression(string $column, string $separator = ','): string
    {
        return match ($this->getDriver()) {
            'mysql' => "GROUP_CONCAT({$column} SEPARATOR '{$separator}')",
            'pgsql' => "STRING_AGG({$column}, '{$separator}')",
            'sqlite' => "GROUP_CONCAT({$column}, '{$separator}')",
            default => "GROUP_CONCAT({$column} SEPARATOR '{$separator}')",
        };
    }

    /**
     * Get the variance function for the current database driver
     */
    public function getVarianceExpression(string $column): string
    {
        return match ($this->getDriver()) {
            'mysql', 'pgsql' => "VARIANCE({$column})",
            'sqlite' => "(AVG({$column} * {$column}) - AVG({$column}) * AVG({$column}))",
            default => "VARIANCE({$column})",
        };
    }

    /**
     * Generate an UPSERT (insert or update) SQL statement.
     *
     * @param array<int, string> $placeholders
     * @param array<int, string> $updateStatements
     * @param array<int, string> $uniqueBy
     * @param array<int, string> $updateColumns
     * @throws \RuntimeException
     */
    public function getUpsertSql(string $columnsStr, array $placeholders, array $updateStatements, array $uniqueBy, array $updateColumns, bool $ignoreErrors): string
    {
        return match ($this->getDriver()) {
            'mysql' => "INSERT " . ($ignoreErrors ? "IGNORE " : "") .
                "INTO `{$this->table}` ({$columnsStr}) VALUES " .
                implode(', ', $placeholders) .
                " ON DUPLICATE KEY UPDATE " .
                implode(', ', $updateStatements),

            'pgsql' => (function () use ($ignoreErrors, $uniqueBy, $columnsStr, $placeholders, $updateColumns, $updateStatements) {
                $uniqueColumns = implode(', ', array_map(fn($col) => "\"{$col}\"", $uniqueBy));

                if ($ignoreErrors) {
                    return "INSERT INTO \"{$this->table}\" ({$columnsStr}) VALUES " .
                        implode(', ', $placeholders) .
                        " ON CONFLICT({$uniqueColumns}) DO NOTHING";
                }

                if (!empty($updateStatements)) {
                    $updateClause = implode(', ', $updateStatements);
                } elseif (!empty($updateColumns)) {
                    $updateStatements = array_map(
                        fn($col) => "\"{$col}\" = EXCLUDED.\"{$col}\"",
                        $updateColumns
                    );
                    $updateClause = implode(', ', $updateStatements);
                } else {
                    $allColumns = $this->getTableColumns();
                    $columnsToUpdate = array_diff($allColumns, $uniqueBy);

                    if (empty($columnsToUpdate)) {
                        throw new \RuntimeException("No columns available to update in UPSERT operation");
                    }

                    $updateStatements = array_map(
                        fn($col) => "\"{$col}\" = EXCLUDED.\"{$col}\"",
                        $columnsToUpdate
                    );
                    $updateClause = implode(', ', $updateStatements);
                }

                return "INSERT INTO \"{$this->table}\" ({$columnsStr}) VALUES " .
                    implode(', ', $placeholders) .
                    " ON CONFLICT({$uniqueColumns}) DO UPDATE SET " .
                    $updateClause;
            })(),

            'sqlite' => (function () use ($ignoreErrors, $uniqueBy, $columnsStr, $placeholders, $updateColumns) {
                if ($this->sqliteSupportsOnConflict()) {
                    $uniqueColumns = implode(', ', array_map(fn($col) => "`$col`", $uniqueBy));
                    if ($ignoreErrors) {
                        return "INSERT INTO `{$this->table}` ({$columnsStr}) VALUES " .
                            implode(', ', $placeholders) .
                            " ON CONFLICT({$uniqueColumns}) DO NOTHING";
                    }
                    $updateStatements = array_map(
                        fn($col) => "`$col` = EXCLUDED.`$col`",
                        $updateColumns
                    );
                    return "INSERT INTO `{$this->table}` ({$columnsStr}) VALUES " .
                        implode(', ', $placeholders) .
                        " ON CONFLICT({$uniqueColumns}) DO UPDATE SET " .
                        implode(', ', $updateStatements);
                }
                return "INSERT OR " . ($ignoreErrors ? "IGNORE" : "REPLACE") .
                    " INTO `{$this->table}` ({$columnsStr}) VALUES " .
                    implode(', ', $placeholders);
            })(),

            default => throw new \RuntimeException("Unsupported database driver: {$this->getDriver()}"),
        };
    }

    /**
     * Check if SQLite version supports ON CONFLICT clause
     */
    protected function sqliteSupportsOnConflict(): bool
    {
        if ($this->pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            return false;
        }

        try {
            $stmt = $this->pdo->query('SELECT sqlite_version()');
            if ($stmt === false) return false;
            
            $version = (string) $stmt->fetchColumn();
            return version_compare($version, '3.24.0', '>=');
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Internal method to handle LIKE conditions with proper case handling for each driver.
     */
    protected function addLikeCondition(string $field, string $value, bool $caseSensitive, string $boolean): self
    {
        $this->assertValidIdentifier($field);

        $driver = $this->getDriver();
        $likeValue = $this->prepareLikeValue($value);

        switch ($driver) {
            case 'pgsql':
                $operator = 'ILIKE';
                break;
            case 'mysql':
            case 'sqlite':
            default:
                $operator = 'LIKE';
        }

        $this->conditions[] = [$boolean, $field, $operator, $likeValue];

        return $this;
    }

    /**
     * Prepare LIKE value by adding wildcards if needed
     */
    protected function prepareLikeValue(string $value): string
    {
        if (str_contains($value, '%') || str_contains($value, '_')) {
            return $value;
        }

        return "%{$value}%";
    }

    /**
     * Check if a column is case-sensitive (MySQL specific)
     */
    protected function isCaseSensitiveColumn(string $field): bool
    {
        try {
            if (str_contains($field, '.')) {
                [$table, $column] = explode('.', $field, 2);
                $table = trim($table, '`');
                $column = trim($column, '`');
            } else {
                $table = $this->table;
                $column = trim($field, '`');
            }

            if ($this->getDriver() !== 'mysql') {
                return true;
            }

            $stmt = $this->pdo->prepare("
            SELECT COLLATION_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
        ");

            if ($stmt === false) {
                return true;
            }

            $stmt->execute([$table, $column]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['COLLATION_NAME'])) {
                $collation = (string) $result['COLLATION_NAME'];
                return str_ends_with($collation, '_cs');
            }

            return true;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Convert LIKE pattern to SQLite GLOB pattern for case-sensitive search
     */
    protected function convertLikeToGlob(string $likePattern): string
    {
        $globPattern = str_replace('%', '*', $likePattern);
        $globPattern = str_replace('_', '?', $globPattern);

        return $globPattern;
    }

    /**
     * Quote identifier based on database driver
     */
    public function quoteIdentifier(string $identifier): string
    {
        $quoteChar = $this->getDriver() === 'pgsql' ? '"' : '`';

        if (strpos($identifier, '.') !== false) {
            $parts = explode('.', $identifier);
            return implode('.', array_map(fn($part) => "{$quoteChar}{$part}{$quoteChar}", $parts));
        }

        return "{$quoteChar}{$identifier}{$quoteChar}";
    }
}