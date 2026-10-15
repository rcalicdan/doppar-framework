<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;
use Phaseolies\Database\Migration\IndexDefinition;

abstract class Grammar
{
    /**
     * Column types that are auto-incrementing primary keys.
     *
     * @var array<int, string>
     */
    protected const INCREMENTING_TYPES = [
        'id',
        'bigIncrements',
        'increments',
        'integerIncrements',
        'tinyIncrements',
        'smallIncrements',
        'mediumIncrements',
    ];

    /**
     * Table level options (engine, charset, collation, comment, temporary).
     *
     * @var array<string, mixed>
     */
    protected array $tableOptions = [];

    /**
     * Set the table level options used by the next CREATE TABLE statement.
     *
     * @param array<string, mixed> $options
     */
    public function setTableOptions(array $options): static
    {
        $this->tableOptions = $options;

        return $this;
    }

    /**
     * Determine whether the given column type is an auto-incrementing key.
     */
    public function isIncrementing(string $type): bool
    {
        return in_array($type, static::INCREMENTING_TYPES, true);
    }

    /**
     * Quote an identifier (table / column / index name).
     */
    public function quoteIdentifier(string $name): string
    {
        return '"' . str_replace('"', '""', trim($name, '"`')) . '"';
    }

    /**
     * Quote a list of identifiers and join them with commas.
     *
     * @param array<int, string> $names
     */
    public function quoteColumns(array $names): string
    {
        return implode(', ', array_map(fn($name) => $this->quoteIdentifier($name), $names));
    }

    /**
     * Quote a string literal.
     */
    public function quoteString(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }

    /**
     * Build the default name of an index for the given table and columns.
     *
     * @param array<int, string> $columns
     */
    public function indexName(string $table, array $columns, string $type = 'index'): string
    {
        $base = $table . '_' . implode('_', $columns);

        $name = match ($type) {
            'index' => 'idx_' . $base,
            default => $base . '_' . $type,
        };

        $name = (string) preg_replace('/[^A-Za-z0-9_]/', '_', $name);

        if (strlen($name) > 63) {
            $name = substr($name, 0, 54) . '_' . substr(md5($name), 0, 8);
        }

        return $name;
    }

    /**
     * Get the SQL appended to a column type (collation, generated column).
     */
    public function compileTypeSuffix(ColumnDefinition $column): string
    {
        $sql = '';
        $attributes = $column->attributes;

        if (!empty($attributes['collation']) && is_string($attributes['collation'])) {
            $sql .= ' COLLATE ' . $this->formatCollation($attributes['collation']);
        }

        $generated = $attributes['storedAs'] ?? $attributes['virtualAs'] ?? null;

        if ($generated !== null) {
            $sql .= " GENERATED ALWAYS AS ({$generated}) "
                . (isset($attributes['storedAs']) ? 'STORED' : 'VIRTUAL');
        }

        return $sql;
    }

    /**
     * Get driver specific modifiers appended after the DEFAULT clause.
     */
    public function compileColumnModifiers(ColumnDefinition $column): string
    {
        return '';
    }

    /**
     * Whether PRIMARY KEY is declared inline in the column definition.
     */
    public function shouldAddPrimaryInColumnDefinition(): bool
    {
        return false;
    }

    /**
     * Whether foreign keys can be added to an existing table.
     */
    public function supportsAddingForeignKey(): bool
    {
        return true;
    }

    /**
     * Append table level constraints to a compiled CREATE TABLE statement.
     *
     * @param array<int, string> $constraints
     */
    public function appendTableConstraints(string $createSql, array $constraints): string
    {
        $position = strrpos($createSql, ')');

        if ($position === false) {
            return $createSql;
        }

        return substr($createSql, 0, $position) . ', ' . implode(', ', $constraints) . substr($createSql, $position);
    }

    /**
     * Compile statements that must follow CREATE TABLE (table comment, ...).
     *
     * @return array<int, string>
     */
    public function compileTableOptionStatements(string $table): array
    {
        return [];
    }

    /**
     * Compile statements that attach a comment to a column when the driver
     * does not support inline comments.
     *
     * @return array<int, string>
     */
    public function compileColumnComment(string $table, ColumnDefinition $column): array
    {
        return [];
    }

    /**
     * Compile a statement that creates a table (used to add TEMPORARY).
     */
    protected function createTableKeyword(): string
    {
        return !empty($this->tableOptions['temporary']) ? 'CREATE TEMPORARY TABLE' : 'CREATE TABLE';
    }

    /**
     * Compile DROP COLUMN statements.
     *
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    public function compileDropColumn(string $table, array $columns): array
    {
        return array_map(
            fn($column) => "ALTER TABLE {$this->quoteIdentifier($table)} DROP COLUMN {$this->quoteIdentifier($column)}",
            $columns
        );
    }

    /**
     * Compile a RENAME COLUMN statement.
     */
    public function compileRenameColumn(string $table, string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} RENAME COLUMN "
            . "{$this->quoteIdentifier($from)} TO {$this->quoteIdentifier($to)}";
    }

    /**
     * Compile a RENAME TABLE statement.
     */
    public function compileRenameTable(string $from, string $to): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}";
    }

    /**
     * Compile a DROP TABLE statement.
     */
    public function compileDropTable(string $table, bool $ifExists = false): string
    {
        return 'DROP TABLE ' . ($ifExists ? 'IF EXISTS ' : '') . $this->quoteIdentifier($table);
    }

    /**
     * Compile a table level index (index, unique, fulltext, spatial).
     */
    public function compileIndex(string $table, IndexDefinition $index): string
    {
        $name = $index->name ?? $this->indexName($table, $index->columns, $index->type);

        return match ($index->type) {
            'index' => $this->compileCreateIndexSql($table, $name, $index->columns, $index->algorithm),
            'unique' => $this->compileCreateUniqueSql($table, $name, $index->columns),
            'fulltext' => $this->compileFullTextIndex($table, $name, $index->columns),
            'spatial' => $this->compileSpatialIndex($table, $name, $index->columns),
            default => throw new \InvalidArgumentException("Unknown index type [{$index->type}]."),
        };
    }

    /**
     * Compile CREATE INDEX for one or more columns.
     *
     * @param array<int, string> $columns
     */
    protected function compileCreateIndexSql(string $table, string $name, array $columns, ?string $algorithm = null): string
    {
        return "CREATE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . ($algorithm ? "USING {$this->safeKeyword($algorithm)} " : '')
            . "({$this->quoteColumns($columns)})";
    }

    /**
     * Compile a UNIQUE constraint for one or more columns.
     *
     * @param array<int, string> $columns
     */
    protected function compileCreateUniqueSql(string $table, string $name, array $columns): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} ADD CONSTRAINT {$this->quoteIdentifier($name)} "
            . "UNIQUE ({$this->quoteColumns($columns)})";
    }

    /**
     * Compile a full text index.
     *
     * @param array<int, string> $columns
     */
    protected function compileFullTextIndex(string $table, string $name, array $columns): string
    {
        throw new \RuntimeException('This database driver does not support full text indexes.');
    }

    /**
     * Compile a spatial index.
     *
     * @param array<int, string> $columns
     */
    protected function compileSpatialIndex(string $table, string $name, array $columns): string
    {
        throw new \RuntimeException('This database driver does not support spatial indexes.');
    }

    /**
     * Compile a statement that drops an index of the given type.
     */
    public function compileDropIndex(string $table, string $name, string $type = 'index'): string
    {
        return match ($type) {
            'primary' => $this->compileDropPrimary($table),
            'foreign' => $this->compileDropForeign($table, $name),
            'unique' => $this->compileDropUnique($table, $name),
            default => 'DROP INDEX ' . $this->quoteIdentifier($name),
        };
    }

    /**
     * Compile a DROP UNIQUE statement.
     */
    protected function compileDropUnique(string $table, string $name): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP CONSTRAINT {$this->quoteIdentifier($name)}";
    }

    /**
     * Compile a DROP PRIMARY KEY statement.
     */
    protected function compileDropPrimary(string $table): string
    {
        throw new \RuntimeException('This database driver cannot drop a primary key.');
    }

    /**
     * Compile a DROP FOREIGN KEY statement.
     */
    protected function compileDropForeign(string $table, string $name): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP CONSTRAINT {$this->quoteIdentifier($name)}";
    }

    /**
     * Compile an ADD PRIMARY KEY statement.
     *
     * @param array<int, string> $columns
     */
    public function compileAddPrimary(string $table, array $columns): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} ADD PRIMARY KEY ({$this->quoteColumns($columns)})";
    }

    /**
     * Compile a RENAME INDEX statement.
     */
    public function compileRenameIndex(string $table, string $from, string $to): string
    {
        throw new \RuntimeException('This database driver cannot rename indexes.');
    }

    /**
     * Compile the statements that modify an existing column.
     *
     * @return array<int, string>
     */
    public function compileChangeColumn(string $table, ColumnDefinition $column): array
    {
        throw new \RuntimeException('This database driver cannot modify existing columns.');
    }

    /**
     * SQL (with a single table-name binding) that lists the column names.
     */
    abstract public function compileGetColumns(): string;

    /**
     * SQL (with a single table-name binding) that lists the index names.
     */
    abstract public function compileGetIndexes(): string;

    /**
     * Format a collation name for use in COLLATE.
     */
    protected function formatCollation(string $collation): string
    {
        return $this->safeKeyword($collation);
    }

    /**
     * Allow only characters that are valid in keywords / charset names.
     *
     * @throws \InvalidArgumentException
     */
    protected function safeKeyword(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_.\-]+$/', $value)) {
            throw new \InvalidArgumentException("Invalid identifier [{$value}].");
        }

        return $value;
    }

    /**
     * Get the SQL type definition for a column.
     */
    abstract public function getTypeDefinition(ColumnDefinition $column): string;

    /**
     * Get the SQL for creating a table.
     *
     * @param array<int, ColumnDefinition> $columns
     * @param array<int, string> $primaryKeys
     */
    abstract public function compileCreateTable(string $table, array $columns, array $primaryKeys = []): string;

    /**
     * Get the SQL for adding a column.
     */
    abstract public function compileAddColumn(string $table, string $columnSql): string;

    /**
     * Get the SQL for creating an index.
     */
    abstract public function compileCreateIndex(string $table, string $column, ?string $name = null): string;

    /**
     * Get the SQL for creating a unique constraint.
     */
    abstract public function compileCreateUnique(string $table, string $column, ?string $name = null): string;

    /**
     * Check if the grammar supports adding columns with AFTER.
     */
    public function supportsColumnOrdering(): bool
    {
        return false;
    }

    /**
     * Check if the grammar supports adding primary key with ALTER TABLE.
     */
    public function supportsAddingPrimaryKey(): bool
    {
        return false;
    }

    /**
     * Check if UNIQUE constraint should be added in column definition.
     */
    public function shouldAddUniqueInColumnDefinition(): bool
    {
        return false;
    }

    /**
     * Extract and validate the allowed values for an enum/set column.
     *
     * @param array<string, mixed> $attributes
     * @return array<int, mixed>
     * @throws \InvalidArgumentException
     */
    protected function getEnumAllowedValues(array $attributes): array
    {
        $values = $attributes['allowed'] ?? $attributes['values'] ?? null;

        if (!$values || !is_array($values)) {
            throw new \InvalidArgumentException('Enum type requires an array of allowed values');
        }

        return array_values($values);
    }

    /**
     * Build an inline CHECK constraint clause restricting a column to a fixed set of values.
     *
     * @param array<int, mixed> $values
     */
    protected function compileEnumCheckClause(string $column, array $values): string
    {
        $quoted = array_map(
            fn($value) => "'" . str_replace("'", "''", (string) $value) . "'",
            $values
        );

        return "CHECK ({$column} IN (" . implode(', ', $quoted) . '))';
    }
}