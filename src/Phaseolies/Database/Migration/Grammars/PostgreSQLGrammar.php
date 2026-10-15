<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

class PostgreSQLGrammar extends Grammar
{
    /**
     * Get the SQL data type definition for a given column.
     */
    #[\Override]
    public function getTypeDefinition(ColumnDefinition $column): string
    {
        $type = $this->mapType($column->type, $column->attributes);

        if ($column->type === 'enum') {
            $values = $this->getEnumAllowedValues($column->attributes);
            $type .= ' ' . $this->compileEnumCheckClause($column->name, $values);
        }

        return $type . $this->compileTypeSuffix($column);
    }

    #[\Override]
    protected function formatCollation(string $collation): string
    {
        return $this->quoteIdentifier($this->safeKeyword($collation));
    }

    /**
     * Get the SERIAL type backing an incrementing column.
     */
    protected function serialType(string $type): string
    {
        return match ($type) {
            'tinyIncrements', 'smallIncrements' => 'SMALLSERIAL',
            'increments', 'integerIncrements', 'mediumIncrements' => 'SERIAL',
            default => 'BIGSERIAL',
        };
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function compileColumnComment(string $table, ColumnDefinition $column): array
    {
        if (!isset($column->attributes['comment']) || !is_string($column->attributes['comment'])) {
            return [];
        }

        return ["COMMENT ON COLUMN {$this->quoteIdentifier($table)}.{$this->quoteIdentifier($column->name)} IS "
            . $this->quoteString($column->attributes['comment'])];
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function compileTableOptionStatements(string $table): array
    {
        if (!isset($this->tableOptions['comment']) || !is_string($this->tableOptions['comment'])) {
            return [];
        }

        return ["COMMENT ON TABLE {$this->quoteIdentifier($table)} IS " . $this->quoteString($this->tableOptions['comment'])];
    }

    /**
     * Compile a SQL CREATE TABLE statement.
     *
     * @param array<int, ColumnDefinition> $columns
     * @param array<int, string> $primaryKeys
     */
    #[\Override]
    public function compileCreateTable(string $table, array $columns, array $primaryKeys = []): string
    {
        $columnDefinitions = [];
        $primaryKeyColumns = [];

        foreach ($columns as $column) {
            $columnSql = $column->toSql();

            if ($this->isIncrementing($column->type)) {
                $columnSql = sprintf(
                    '"%s" %s NOT NULL',
                    $column->name,
                    $this->serialType($column->type)
                );
                $primaryKeyColumns[] = trim($column->name, '"');
            } elseif ($column->type === 'uuid' && !empty($column->attributes['primary'])) {
                $columnSql = sprintf(
                    '"%s" UUID NOT NULL DEFAULT gen_random_uuid()',
                    $column->name
                );
                $primaryKeyColumns[] = trim($column->name, '"');
            } elseif (isset($column->attributes['primary']) && $column->attributes['primary']) {
                $primaryKeyColumns[] = trim($column->name, '"');
            }

            $columnDefinitions[] = $columnSql;
        }

        $primaryKeySql = '';
        if (!empty($primaryKeyColumns)) {
            $primaryKeySql = ', PRIMARY KEY ("' . implode('", "', $primaryKeyColumns) . '")';
        }

        return $this->createTableKeyword() . " \"{$table}\" (" . implode(', ', $columnDefinitions) . $primaryKeySql . ')';
    }

    #[\Override]
    public function compileAddColumn(string $table, string $columnSql): string
    {
        return "ALTER TABLE \"{$table}\" ADD COLUMN {$columnSql}";
    }

    #[\Override]
    public function compileCreateIndex(string $table, string $column, ?string $name = null): string
    {
        return $this->compileCreateIndexSql($table, $name ?? $this->indexName($table, [$column]), [$column]);
    }

    #[\Override]
    public function compileCreateUnique(string $table, string $column, ?string $name = null): string
    {
        return $this->compileCreateUniqueSql($table, $name ?? $this->indexName($table, [$column], 'unique'), [$column]);
    }

    /**
     * @param array<int, string> $columns
     */
    #[\Override]
    protected function compileFullTextIndex(string $table, string $name, array $columns): string
    {
        $vector = implode(" || ' ' || ", array_map(
            fn($column) => "coalesce({$this->quoteIdentifier($column)}, '')",
            $columns
        ));

        return "CREATE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "USING GIN (to_tsvector('english', {$vector}))";
    }

    /**
     * @param array<int, string> $columns
     */
    #[\Override]
    protected function compileSpatialIndex(string $table, string $name, array $columns): string
    {
        return "CREATE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "USING GIST ({$this->quoteColumns($columns)})";
    }

    #[\Override]
    protected function compileDropPrimary(string $table): string
    {
        return "ALTER TABLE {$this->quoteIdentifier($table)} DROP CONSTRAINT {$this->quoteIdentifier($table . '_pkey')}";
    }

    #[\Override]
    public function compileRenameIndex(string $table, string $from, string $to): string
    {
        return "ALTER INDEX {$this->quoteIdentifier($from)} RENAME TO {$this->quoteIdentifier($to)}";
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function compileChangeColumn(string $table, ColumnDefinition $column): array
    {
        $t = $this->quoteIdentifier($table);
        $c = $this->quoteIdentifier($column->name);
        $type = $this->mapType($column->type, $column->attributes);

        if ($this->isIncrementing($column->type)) {
            $type = match ($column->type) {
                'tinyIncrements', 'smallIncrements' => 'SMALLINT',
                'increments', 'integerIncrements', 'mediumIncrements' => 'INTEGER',
                default => 'BIGINT',
            };
        } elseif (!empty($column->attributes['autoIncrement'])) {
            $type = $this->mapType($column->type, array_diff_key($column->attributes, ['autoIncrement' => 1]));
        }

        $statements = ["ALTER TABLE {$t} ALTER COLUMN {$c} TYPE {$type} USING {$c}::{$type}"];

        $statements[] = "ALTER TABLE {$t} ALTER COLUMN {$c} "
            . (!empty($column->attributes['nullable']) ? 'DROP NOT NULL' : 'SET NOT NULL');

        $default = $column->getDefaultSql();

        $statements[] = "ALTER TABLE {$t} ALTER COLUMN {$c} "
            . ($default !== null ? "SET DEFAULT {$default}" : 'DROP DEFAULT');

        return array_merge($statements, $this->compileColumnComment($table, $column));
    }

    #[\Override]
    public function compileGetColumns(): string
    {
        return 'SELECT column_name FROM information_schema.columns '
            . 'WHERE table_schema = current_schema() AND table_name = ? ORDER BY ordinal_position';
    }

    #[\Override]
    public function compileGetIndexes(): string
    {
        return 'SELECT indexname FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ?';
    }

    #[\Override]
    public function supportsColumnOrdering(): bool
    {
        return true;
    }

    #[\Override]
    public function supportsAddingPrimaryKey(): bool
    {
        return true;
    }

    /**
     * Map abstract column types to PostgreSQL-specific SQL type definitions.
     *
     * @param array<string, mixed> $attributes
     */
    protected function mapType(string $type, array $attributes): string
    {
        switch ($type) {
            case 'enum':
                return $this->createEnumType($attributes);
            case 'set':
                return 'TEXT[]';
            case 'string':
                return 'VARCHAR(' . ($attributes['length'] ?? 255) . ')';
            case 'char':
                return 'CHAR(' . ($attributes['length'] ?? 255) . ')';
            case 'decimal':
                return 'DECIMAL(' . ($attributes['precision'] ?? 10) . ',' . ($attributes['scale'] ?? 2) . ')';
            case 'double':
                return 'DOUBLE PRECISION';
            case 'timestamp':
                return 'TIMESTAMP' . $this->getTimePrecision($attributes);
            case 'timestampTz':
                return 'TIMESTAMPTZ' . $this->getTimePrecision($attributes);
            case 'dateTime':
                return 'TIMESTAMP' . $this->getTimePrecision($attributes);
            case 'dateTimeTz':
                return 'TIMESTAMPTZ' . $this->getTimePrecision($attributes);
            case 'time':
                return 'TIME' . $this->getTimePrecision($attributes);
            case 'timeTz':
                return 'TIMETZ' . $this->getTimePrecision($attributes);
            case 'integer':
            case 'bigInteger':
            case 'smallInteger':
                if (!empty($attributes['autoIncrement'])) {
                    return match ($type) {
                        'bigInteger' => 'BIGSERIAL',
                        'smallInteger' => 'SMALLSERIAL',
                        default => 'SERIAL',
                    };
                }
                break;
            case 'jsonb':
                return 'JSONB';
        }

        $map = [
            'id' => 'BIGSERIAL',
            'bigIncrements' => 'BIGSERIAL',
            'increments' => 'SERIAL',
            'integerIncrements' => 'SERIAL',
            'tinyIncrements' => 'SMALLSERIAL',
            'smallIncrements' => 'SMALLSERIAL',
            'mediumIncrements' => 'SERIAL',
            'ulid' => 'CHAR(26)',
            'text' => 'TEXT',
            'mediumText' => 'TEXT',
            'longText' => 'TEXT',
            'tinyText' => 'TEXT',
            'boolean' => 'BOOLEAN',
            'json' => 'JSON',
            'integer' => 'INTEGER',
            'tinyInteger' => 'SMALLINT',
            'smallInteger' => 'SMALLINT',
            'mediumInteger' => 'INTEGER',
            'bigInteger' => 'BIGINT',
            'unsignedInteger' => 'INTEGER',
            'unsignedTinyInteger' => 'SMALLINT',
            'unsignedSmallInteger' => 'SMALLINT',
            'unsignedMediumInteger' => 'INTEGER',
            'unsignedBigInteger' => 'BIGINT',
            'float' => 'REAL',
            'date' => 'DATE',
            'dateTime' => 'TIMESTAMP',
            'dateTimeTz' => 'TIMESTAMPTZ',
            'time' => 'TIME',
            'timeTz' => 'TIMETZ',
            'timestamp' => 'TIMESTAMP',
            'timestampTz' => 'TIMESTAMPTZ',
            'year' => 'INTEGER',
            'binary' => 'BYTEA',
            'tinyBlob' => 'BYTEA',
            'mediumBlob' => 'BYTEA',
            'longBlob' => 'BYTEA',
            'geometry' => 'GEOMETRY',
            'point' => 'POINT',
            'lineString' => 'LINESTRING',
            'polygon' => 'POLYGON',
            'geometryCollection' => 'GEOMETRYCOLLECTION',
            'multiPoint' => 'MULTIPOINT',
            'multiLineString' => 'MULTILINESTRING',
            'multiPolygon' => 'MULTIPOLYGON',
            'uuid' => 'UUID',
            'ipAddress' => 'INET',
            'macAddress' => 'MACADDR',
        ];

        return $map[$type] ?? strtoupper($type);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function getTimePrecision(array $attributes): string
    {
        return isset($attributes['precision']) ? '(' . (int) $attributes['precision'] . ')' : '';
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function createEnumType(array $attributes): string
    {
        $this->getEnumAllowedValues($attributes);

        return 'TEXT';
    }

    public function compileCreateSchema(string $schema): string
    {
        return "CREATE SCHEMA IF NOT EXISTS \"{$schema}\"";
    }

    public function compileDropSchema(string $schema): string
    {
        return "DROP SCHEMA IF EXISTS \"{$schema}\" CASCADE";
    }

    public function compileSetSearchPath(string $schema): string
    {
        return "SET search_path TO \"{$schema}\"";
    }
}