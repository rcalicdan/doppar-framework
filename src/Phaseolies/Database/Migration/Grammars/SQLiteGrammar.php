<?php

namespace Phaseolies\Database\Migration\Grammars;

use Phaseolies\Database\Migration\ColumnDefinition;

class SQLiteGrammar extends Grammar
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
    public function quoteIdentifier(string $name): string
    {
        return '`' . str_replace('`', '``', trim($name, '"`')) . '`';
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
        $hasAutoIncrementId = false;
        $tablePrimaryKeys = [];

        foreach ($columns as $column) {
            $originalSql = $column->toSql();
            $columnSql = $originalSql;

            $isPrimaryKey = !empty($column->attributes['primary']) ||
                           in_array($column->name, $primaryKeys) ||
                           $this->isIncrementing($column->type);

            if ($isPrimaryKey) {
                if ($this->isIncrementing($column->type)) {
                    $cleanSql = preg_replace('/\s+PRIMARY\s+KEY/i', '', $originalSql) ?? $originalSql;
                    $columnSql = str_replace('INTEGER', 'INTEGER PRIMARY KEY AUTOINCREMENT', $cleanSql);
                    $hasAutoIncrementId = true;
                } elseif ($column->type === 'uuid' && !empty($column->attributes['primary'])) {
                    $columnSql = preg_replace('/\s+PRIMARY\s+KEY/i', '', $originalSql) ?? $originalSql;
                    $tablePrimaryKeys[] = $column->name;
                    $hasAutoIncrementId = true;
                } elseif (!empty($column->attributes['primary'])) {
                    $tablePrimaryKeys[] = $column->name;
                    $columnSql = preg_replace('/\s+PRIMARY\s+KEY/i', '', $originalSql) ?? $originalSql;
                }
            }

            $columnDefinitions[] = $columnSql;
        }

        $primaryKeySql = '';

        $hasExplicitPrimaryKey = false;
        foreach ($columnDefinitions as $def) {
            if (stripos($def, 'PRIMARY KEY') !== false) {
                $hasExplicitPrimaryKey = true;
                break;
            }
        }

        if ((!empty($tablePrimaryKeys) || count($primaryKeys) > 0) && !$hasAutoIncrementId && !$hasExplicitPrimaryKey) {
            $keys = !empty($tablePrimaryKeys) ? $tablePrimaryKeys : $primaryKeys;
            $primaryKeySql = ', PRIMARY KEY (`' . implode('`, `', $keys) . '`)';
        }

        return $this->createTableKeyword() . " `{$table}` (" . implode(', ', $columnDefinitions) . $primaryKeySql . ')';
    }

    #[\Override]
    public function compileAddColumn(string $table, string $columnSql): string
    {
        return "ALTER TABLE `{$table}` ADD COLUMN {$columnSql}";
    }

    #[\Override]
    public function compileCreateIndex(string $table, string $column, ?string $name = null): string
    {
        return $this->compileCreateIndexSql($table, $name ?? $this->indexName($table, [$column]), [$column]);
    }

    #[\Override]
    public function compileCreateUnique(string $table, string $column, ?string $name = null): string
    {
        return '';
    }

    /**
     * @param array<int, string> $columns
     */
    #[\Override]
    protected function compileCreateUniqueSql(string $table, string $name, array $columns): string
    {
        return "CREATE UNIQUE INDEX {$this->quoteIdentifier($name)} ON {$this->quoteIdentifier($table)} "
            . "({$this->quoteColumns($columns)})";
    }

    #[\Override]
    protected function compileDropUnique(string $table, string $name): string
    {
        return 'DROP INDEX ' . $this->quoteIdentifier($name);
    }

    /**
     * @param array<int, string> $columns
     */
    #[\Override]
    public function compileAddPrimary(string $table, array $columns): string
    {
        throw new \RuntimeException(
            'SQLite cannot add a primary key to an existing table. Declare it in the CREATE TABLE migration.'
        );
    }

    #[\Override]
    protected function compileDropPrimary(string $table): string
    {
        throw new \RuntimeException('SQLite cannot drop a primary key from an existing table.');
    }

    #[\Override]
    protected function compileDropForeign(string $table, string $name): string
    {
        throw new \RuntimeException('SQLite cannot drop a foreign key from an existing table.');
    }

    /**
     * @return array<int, string>
     */
    #[\Override]
    public function compileChangeColumn(string $table, ColumnDefinition $column): array
    {
        throw new \RuntimeException(
            "SQLite cannot modify column \"{$column->name}\" on table \"{$table}\". "
            . 'Create a new table, copy the data and drop the old one instead.'
        );
    }

    #[\Override]
    public function supportsAddingForeignKey(): bool
    {
        return false;
    }

    #[\Override]
    public function shouldAddPrimaryInColumnDefinition(): bool
    {
        return true;
    }

    #[\Override]
    public function compileGetColumns(): string
    {
        return 'SELECT name FROM pragma_table_info(?)';
    }

    #[\Override]
    public function compileGetIndexes(): string
    {
        return 'SELECT name FROM pragma_index_list(?)';
    }

    #[\Override]
    public function supportsAddingPrimaryKey(): bool
    {
        return false;
    }

    #[\Override]
    public function shouldAddUniqueInColumnDefinition(): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function mapType(string $type, array $attributes): string
    {
        $map = [
            'id' => 'INTEGER',
            'bigIncrements' => 'INTEGER',
            'increments' => 'INTEGER',
            'integerIncrements' => 'INTEGER',
            'tinyIncrements' => 'INTEGER',
            'smallIncrements' => 'INTEGER',
            'mediumIncrements' => 'INTEGER',
            'ulid' => 'TEXT',
            'string' => 'TEXT',
            'char' => 'TEXT',
            'text' => 'TEXT',
            'mediumText' => 'TEXT',
            'longText' => 'TEXT',
            'tinyText' => 'TEXT',
            'boolean' => 'INTEGER',
            'json' => 'TEXT',
            'jsonb' => 'TEXT',
            'integer' => 'INTEGER',
            'tinyInteger' => 'INTEGER',
            'smallInteger' => 'INTEGER',
            'mediumInteger' => 'INTEGER',
            'bigInteger' => 'INTEGER',
            'unsignedInteger' => 'INTEGER',
            'unsignedTinyInteger' => 'INTEGER',
            'unsignedSmallInteger' => 'INTEGER',
            'unsignedMediumInteger' => 'INTEGER',
            'unsignedBigInteger' => 'INTEGER',
            'float' => 'REAL',
            'double' => 'REAL',
            'decimal' => 'REAL',
            'date' => 'TEXT',
            'dateTime' => 'TEXT',
            'dateTimeTz' => 'TEXT',
            'time' => 'TEXT',
            'timeTz' => 'TEXT',
            'timestamp' => 'INTEGER',
            'timestampTz' => 'INTEGER',
            'year' => 'INTEGER',
            'binary' => 'BLOB',
            'tinyBlob' => 'BLOB',
            'mediumBlob' => 'BLOB',
            'longBlob' => 'BLOB',
            'enum' => 'TEXT',
            'set' => 'TEXT',
            'geometry' => 'BLOB',
            'point' => 'BLOB',
            'lineString' => 'BLOB',
            'polygon' => 'BLOB',
            'geometryCollection' => 'BLOB',
            'multiPoint' => 'BLOB',
            'multiLineString' => 'BLOB',
            'multiPolygon' => 'BLOB',
            'uuid' => 'TEXT',
            'ipAddress' => 'TEXT',
            'macAddress' => 'TEXT',
        ];

        return $map[$type] ?? strtoupper($type);
    }
}