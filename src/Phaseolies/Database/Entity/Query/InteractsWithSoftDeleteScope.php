<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Database\Entity\SoftDeletes\SoftDeleteManager;

trait InteractsWithSoftDeleteScope
{
    /**
     * Which rows a soft-deletable query returns: 'exclude' hides trashed
     * rows, 'include' returns everything, 'only' returns trashed rows only.
     */
    protected string $trashed = 'exclude';

    /**
     * When true, delete() removes rows even on a soft-deletable model.
     */
    protected bool $forceDeleting = false;

    /**
     * Include soft-deleted rows in the results.
     *
     * @throws \LogicException
     */
    public function withTrashed(): self
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->trashed = 'include';

        return $this;
    }

    /**
     * Return only soft-deleted rows.
     *
     * @throws \LogicException
     */
    public function onlyTrashed(): self
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->trashed = 'only';

        return $this;
    }

    /**
     * Exclude soft-deleted rows from the results (the default).
     *
     * @throws \LogicException
     */
    public function withoutTrashed(): self
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->trashed = 'exclude';

        return $this;
    }

    /**
     * Turn the soft delete constraint off for this query.
     */
    public function withoutSoftDeleteScope(): self
    {
        $this->trashed = 'include';

        return $this;
    }

    /**
     * Restore the soft-deleted rows matched by the query.
     *
     * @throws \LogicException
     */
    public function restore(): bool
    {
        $this->assertSoftDeletable(__FUNCTION__);

        if ($this->trashed === 'exclude') {
            $this->trashed = 'only';
        }

        return $this->writeSoftDeleteState(null, (string) now());
    }

    /**
     * Write the soft delete column on the matched rows, touching
     * updated_at with the given timestamp when the model uses timestamps.
     */
    public function writeSoftDeleteState(?string $deletedAt, string $timestamp): bool
    {
        $column = $this->getSoftDeleteColumn();

        if ($column === null) {
            return false;
        }

        $attributes = [$column => $deletedAt];

        if ($this->getModel()->usesTimestamps()) {
            $attributes['updated_at'] = $timestamp;
        }

        return $this->performUpdate($attributes);
    }

    /**
     * Permanently delete the rows matched by the query.
     *
     * @throws \LogicException
     */
    public function forceDelete(): bool
    {
        $this->assertSoftDeletable(__FUNCTION__);

        $this->forceDeleting = true;

        try {
            return $this->delete();
        } finally {
            $this->forceDeleting = false;
        }
    }

    /**
     * Determine whether delete() on this query should stamp rows
     * instead of removing them.
     */
    protected function shouldSoftDelete(): bool
    {
        return !$this->forceDeleting && $this->getSoftDeleteColumn() !== null;
    }

    /**
     * Get the soft delete column of the query's model, if it has one.
     */
    public function getSoftDeleteColumn(): ?string
    {
        return SoftDeleteManager::column($this->modelClass);
    }

    /**
     * Build the soft delete constraint for this query.
     */
    public function compileSoftDeleteConstraint(?string $table = null): ?string
    {
        $column = $this->getSoftDeleteColumn();

        if ($column === null || $this->trashed === 'include') {
            return null;
        }

        $qualified = $table === null
            ? $this->tableReference() . '.' . $column
            : $this->quoteIdentifier("{$table}.{$column}");

        return $this->trashed === 'only'
            ? "{$qualified} IS NOT NULL"
            : "{$qualified} IS NULL";
    }

    /**
     * Get the name the query's FROM clause refers to its table by.
     */
    protected function tableReference(): string
    {
        $table = trim($this->table);

        if (preg_match('/^\S+\s+(?:as\s+)?(\S+)$/i', $table, $matches)) {
            return $matches[1];
        }

        return $table;
    }

    /**
     * Build the default soft delete constraint for a related model.
     *
     * @param class-string<\Phaseolies\Database\Entity\Model> $modelClass
     */
    protected function relatedSoftDeleteClause(string $modelClass, string $table): string
    {
        $column = SoftDeleteManager::column($modelClass);

        if ($column === null) {
            return '';
        }

        return ' AND ' . $this->quoteIdentifier("{$table}.{$column}") . ' IS NULL';
    }

    /**
     * Build the WHERE clause, appending the soft delete constraint to
     * the user's conditions.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function buildWhereClause(): array
    {
        [$sql, $bindings] = $this->buildConditionWhereClause();

        $constraint = $this->compileSoftDeleteConstraint();

        if ($constraint === null) {
            return [$sql, $bindings];
        }

        return [$sql === '' ? $constraint : "({$sql}) AND {$constraint}", $bindings];
    }

    /**
     * @throws \LogicException
     */
    protected function assertSoftDeletable(string $method): void
    {
        if ($this->getSoftDeleteColumn() === null) {
            throw new \LogicException(
                "Cannot call {$method}() on {$this->modelClass}: the model is not marked #[SoftDeletes]."
            );
        }
    }
}