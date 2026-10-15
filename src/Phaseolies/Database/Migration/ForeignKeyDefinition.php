<?php

namespace Phaseolies\Database\Migration;

class ForeignKeyDefinition
{
    /**
     * The table blueprint that contains this foreign key.
     */
    protected Blueprint $blueprint;

    /**
     * The column name(s) that will be the foreign key
     * 
     * @var string|array<int, string> 
     */
    protected string|array $column;

    /**
     * The referenced table name
     */
    protected string $on = '';

    /**
     * The referenced column name(s) (default: 'id')
     * 
     * @var string|array<int, string> 
     */
    protected string|array $references = 'id';

    /**
     * Custom constraint name
     */
    protected ?string $name = null;

    /**
     * The ON DELETE action (e.g., CASCADE, SET NULL)
     */
    protected ?string $onDelete = null;

    /**
     * The ON UPDATE action (e.g., CASCADE, SET NULL)
     */
    protected ?string $onUpdate = null;

    /**
     * Create a new foreign key definition instance.
     *
     * @param string|array<int, string> $column
     */
    public function __construct(Blueprint $blueprint, string|array $column)
    {
        $this->blueprint = $blueprint;
        $this->column = $column;
    }

    /**
     * Set the referenced column name(s) in the foreign table.
     *
     * @param string|array<int, string> $column
     */
    public function references(string|array $column): self
    {
        $this->references = $column;

        return $this;
    }

    /**
     * Set the referenced table name.
     */
    public function on(string $table): self
    {
        $this->on = $table;

        return $this;
    }

    /**
     * Set the ON DELETE action for the foreign key.
     */
    public function onDelete(string $action): self
    {
        $this->onDelete = strtoupper($action);

        return $this;
    }

    /**
     * Set the ON UPDATE action for the foreign key.
     */
    public function onUpdate(string $action): self
    {
        $this->onUpdate = strtoupper($action);

        return $this;
    }

    /**
     * Set a custom name for the foreign key constraint.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function noActionOnDelete(): self
    {
        return $this->onDelete('NO ACTION');
    }

    public function noActionOnUpdate(): self
    {
        return $this->onUpdate('NO ACTION');
    }

    public function cascadeOnDelete(): self
    {
        return $this->onDelete('CASCADE');
    }

    public function restrictOnDelete(): self
    {
        return $this->onDelete('RESTRICT');
    }

    public function nullOnDelete(): self
    {
        return $this->onDelete('SET NULL');
    }

    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('CASCADE');
    }

    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('RESTRICT');
    }

    public function nullOnUpdate(): self
    {
        return $this->onUpdate('SET NULL');
    }

    public function getName(): string
    {
        return $this->name ?? $this->getConstraintName();
    }

    /**
     * Convert the constraint to its table level SQL fragment.
     *
     * @throws \RuntimeException
     */
    public function toConstraintSql(): string
    {
        if (!$this->references || !$this->on) {
            throw new \RuntimeException('Foreign key constraint is incomplete. Missing references or on table.');
        }

        $columns = implode(', ', (array) $this->column);
        $references = implode(', ', (array) $this->references);

        $sql = "CONSTRAINT {$this->getName()} FOREIGN KEY ({$columns}) REFERENCES {$this->on} ({$references})";

        if ($this->onDelete) {
            $sql .= " ON DELETE {$this->onDelete}";
        }

        if ($this->onUpdate) {
            $sql .= " ON UPDATE {$this->onUpdate}";
        }

        return $sql;
    }

    /**
     * Convert the foreign key definition to its SQL representation.
     *
     * @throws \RuntimeException
     */
    public function toSql(): string
    {
        return "ALTER TABLE {$this->blueprint->table} ADD " . $this->toConstraintSql();
    }

    protected function getConstraintName(): string
    {
        return 'fk_' . $this->blueprint->table . '_' . implode('_', (array) $this->column);
    }
}
