<?php

namespace Phaseolies\Database\Entity\SoftDeletes;

trait InteractsWithSoftDeletes
{
    /**
     * True while forceDelete() is removing the model, so delete hooks
     * can tell a permanent delete from a soft one.
     */
    protected bool $forceDeleting = false;

    /**
     * Determine whether the model is marked #[SoftDeletes].
     */
    public function usesSoftDeletes(): bool
    {
        return SoftDeleteManager::isSoftDeletable(static::class);
    }

    /**
     * Get the soft delete column, or null when the model is not soft-deletable.
     */
    public function getSoftDeleteColumn(): ?string
    {
        return SoftDeleteManager::column(static::class);
    }

    /**
     * Determine whether the model has been soft-deleted.
     */
    public function trashed(): bool
    {
        $column = $this->getSoftDeleteColumn();

        return $column !== null && ($this->attributes[$column] ?? null) !== null;
    }

    /**
     * Determine whether the model is currently being force deleted.
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting;
    }

    /**
     * Permanently delete the model, bypassing soft deletes.
     *
     * @throws \LogicException
     */
    public function forceDelete(): bool
    {
        $this->assertUsesSoftDeletes(__FUNCTION__);

        $this->forceDeleting = true;

        try {
            return $this->delete();
        } finally {
            $this->forceDeleting = false;
        }
    }

    /**
     * Restore a soft-deleted model.
     *
     * @throws \LogicException
     */
    public function restore(): bool
    {
        $this->assertUsesSoftDeletes(__FUNCTION__);

        if (!isset($this->attributes[$this->primaryKey])) {
            return false;
        }

        try {
            if (self::$isHookShouldBeCalled && $this->fireBeforeHooks('restored') === false) {
                return false;
            }

            $now = (string) now();

            $result = $this->newQuery()
                ->withoutSoftDeleteScope()
                ->where($this->primaryKey, $this->attributes[$this->primaryKey])
                ->writeSoftDeleteState(null, $now);

            if ($result) {
                $this->syncSoftDeleteAttributes(null, $now);

                if (self::$isHookShouldBeCalled) {
                    $this->fireAfterHooks('restored');
                }
            }

            return $result;
        } finally {
            self::$isHookShouldBeCalled = true;
        }
    }

    /**
     * Stamp the soft delete column on the model's row.
     */
    protected function performSoftDelete(): bool
    {
        $now = (string) now();

        $result = $this->newQuery()
            ->withoutSoftDeleteScope()
            ->where($this->primaryKey, $this->attributes[$this->primaryKey])
            ->writeSoftDeleteState($now, $now);

        if ($result) {
            $this->syncSoftDeleteAttributes($now, $now);
        }

        return $result;
    }

    /**
     * Mirror the values a soft delete or restore wrote onto the in-memory
     * model, so it matches the row without a reload.
     */
    private function syncSoftDeleteAttributes(?string $deletedAt, string $timestamp): void
    {
        $column = $this->getSoftDeleteColumn();
        
        $attributes = [];

        if ($column !== null) {
            $attributes[$column] = $deletedAt;
        }

        if ($this->usesTimestamps()) {
            $attributes['updated_at'] = $timestamp;
        }

        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
            $this->originalAttributes[$key] = $value;
        }
    }

    /**
     * @throws \LogicException
     */
    private function assertUsesSoftDeletes(string $method): void
    {
        if (!$this->usesSoftDeletes()) {
            throw new \LogicException(
                "Cannot call {$method}() on " . static::class . ': the model is not marked #[SoftDeletes].'
            );
        }
    }
}