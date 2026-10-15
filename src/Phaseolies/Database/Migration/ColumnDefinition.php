<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Database\Query\RawExpression;
use PDO;

class ColumnDefinition
{
    /**
     * The name of the column.
     */
    public string $name;

    /**
     * The data type of the column.
     */
    public string $type;

    /**
     * Additional attributes/constraints for the column
     * 
     * @var array<string, mixed>
     */
    public array $attributes = [];

    /**
     * Create a new column definition instance.
     *
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->name = $attributes['name'];
        $this->type = $attributes['type'];
        $this->attributes = $attributes;
    }

    /**
     * Set the column as nullable (allowing NULL values).
     */
    public function nullable(bool $value = true): self
    {
        $this->attributes['nullable'] = $value;

        return $this;
    }

    /**
     * Set a default value for the column.
     */
    public function default(mixed $value): self
    {
        $driver = $this->getDriver();

        if ($driver === 'pgsql' && is_bool($value)) {
            $this->attributes['default'] = new RawExpression($value ? 'TRUE' : 'FALSE');
        } else {
            $this->attributes['default'] = $value;
        }

        return $this;
    }

    /**
     * Set the column as unique, optionally with a custom constraint name.
     */
    public function unique(?string $name = null): self
    {
        $this->attributes['unique'] = $name ?? true;

        return $this;
    }

    /**
     * Set the column to be indexed, optionally with a custom index name.
     */
    public function index(?string $name = null): self
    {
        $this->attributes['index'] = $name ?? true;

        return $this;
    }

    /**
     * Add a full text index on the column.
     */
    public function fullText(?string $name = null): self
    {
        $this->attributes['fulltext'] = $name ?? true;

        return $this;
    }

    /**
     * Add a spatial index on the column.
     */
    public function spatialIndex(?string $name = null): self
    {
        $this->attributes['spatial'] = $name ?? true;

        return $this;
    }

    /**
     * Set the column as primary key.
     */
    public function primary(): self
    {
        $this->attributes['primary'] = true;

        return $this;
    }

    /**
     * Set the column for after a column.
     */
    public function after(string $column): self
    {
        $this->attributes['after'] = $column;

        return $this;
    }

    /**
     * Place the column first in the table (MySQL, ALTER TABLE only).
     */
    public function first(): self
    {
        $this->attributes['first'] = true;

        return $this;
    }

    /**
     * Add a comment to the column (MySQL and PostgreSQL).
     */
    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;

        return $this;
    }

    /**
     * Mark a numeric column as UNSIGNED (MySQL only, ignored elsewhere).
     */
    public function unsigned(): self
    {
        $this->attributes['unsigned'] = true;

        return $this;
    }

    /**
     * Use CURRENT_TIMESTAMP as the column default.
     */
    public function useCurrent(): self
    {
        $this->attributes['default'] = new RawExpression('CURRENT_TIMESTAMP');

        return $this;
    }

    /**
     * Refresh the column with CURRENT_TIMESTAMP on every update (MySQL only).
     */
    public function useCurrentOnUpdate(): self
    {
        $this->attributes['useCurrentOnUpdate'] = true;

        return $this;
    }

    /**
     * Make an integer column auto-incrementing (it must also be a key in MySQL).
     */
    public function autoIncrement(): self
    {
        $this->attributes['autoIncrement'] = true;

        return $this;
    }

    /**
     * Set the character set of the column (MySQL only).
     */
    public function charset(string $charset): self
    {
        $this->attributes['charset'] = $charset;

        return $this;
    }

    /**
     * Set the collation of the column.
     */
    public function collation(string $collation): self
    {
        $this->attributes['collation'] = $collation;

        return $this;
    }

    /**
     * Create a stored generated column from the given SQL expression.
     */
    public function storedAs(string $expression): self
    {
        $this->attributes['storedAs'] = $expression;

        return $this;
    }

    /**
     * Create a virtual generated column from the given SQL expression.
     */
    public function virtualAs(string $expression): self
    {
        $this->attributes['virtualAs'] = $expression;

        return $this;
    }

    /**
     * Hide the column from SELECT * (MySQL 8+ only).
     */
    public function invisible(): self
    {
        $this->attributes['invisible'] = true;

        return $this;
    }

    /**
     * Modify the existing column to match this definition (Schema::table only).
     */
    public function change(): self
    {
        $this->attributes['change'] = true;

        return $this;
    }

    /**
     * Get the SQL for the DEFAULT value, or null when none is set.
     */
    public function getDefaultSql(): ?string
    {
        if (!isset($this->attributes['default'])) {
            return null;
        }

        $value = $this->attributes['default'];

        return match (true) {
            $value instanceof RawExpression => (string) $value->getValue(),
            is_string($value) => $this->getGrammar()->quoteString($value),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Convert the column definition to its SQL representation.
     */
    public function toSql(): string
    {
        $grammar = $this->getGrammar();
        $sql = $this->name . ' ' . $grammar->getTypeDefinition($this);

        if (
            !empty($this->attributes['primary']) &&
            ($grammar->shouldAddPrimaryInColumnDefinition() || !empty($this->attributes['altering']))
        ) {
            $sql .= ' PRIMARY KEY';
        }

        if (
            !empty($this->attributes['unique']) &&
            $grammar->shouldAddUniqueInColumnDefinition() &&
            empty($this->attributes['altering'])
        ) {
            $sql .= ' UNIQUE';
        }

        if (isset($this->attributes['nullable']) && $this->attributes['nullable']) {
            $sql .= ' NULL';
        } else {
            $sql .= ' NOT NULL';
        }

        $default = $this->getDefaultSql();

        if ($default !== null && !isset($this->attributes['storedAs']) && !isset($this->attributes['virtualAs'])) {
            $sql .= " DEFAULT {$default}";
        }

        $sql .= $grammar->compileColumnModifiers($this);

        if ($this->getDriver() === 'mysql' && !empty($this->attributes['altering'])) {
            if (!empty($this->attributes['first'])) {
                $sql .= ' FIRST';
            } elseif (isset($this->attributes['after'])) {
                $sql .= " AFTER {$this->attributes['after']}";
            }
        }

        return $sql;
    }

    /**
     * Get the appropriate grammar instance based on the database driver.
     */
    protected function getGrammar(): Grammars\Grammar
    {
        $driver = $this->getDriver();

        return match ($driver) {
            'mysql' => new Grammars\MySQLGrammar(),
            'sqlite' => new Grammars\SQLiteGrammar(),
            'pgsql' => new Grammars\PostgreSQLGrammar(),
            default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
        };
    }

    /**
     * Get the current PDO driver.
     */
    public function getDriver(): string
    {
        $connection = app('db')->getConnection();

        return strtolower($connection->getAttribute(PDO::ATTR_DRIVER_NAME));
    }
}
