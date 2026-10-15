<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Support\Collection;
use Phaseolies\Database\Entity\Model;

trait CollectsRelations
{
    /**
     * Collect all related models from a collection for a given relation.
     *
     * @param Collection<int|string, mixed> $collection
     * @return array{0: array<int, mixed>, 1: class-string<Model>|null} [models, modelClass]
     */
    protected function collectRelatedModels(Collection $collection, string $relation): array
    {
        $allRelatedModels = [];
        /** @var class-string<Model>|null */
        $relatedModelClass = null;

        foreach ($collection->all() as $model) {
            if (is_object($model) && method_exists($model, 'relationLoaded') && $model->relationLoaded($relation)) {
                $related = $model->getRelation($relation);

                if ($related instanceof Collection) {
                    foreach ($related->all() as $relatedModel) {
                        $allRelatedModels[] = $relatedModel;
                    }
                    if (!$relatedModelClass && $related->count() > 0) {
                        $firstRelated = $related->first();
                        if (is_object($firstRelated)) {
                            $relatedModelClass = get_class($firstRelated);
                        }
                    }
                } elseif ($related !== null) {
                    $allRelatedModels[] = $related;
                    if (!$relatedModelClass && is_object($related)) {
                        $relatedModelClass = get_class($related);
                    }
                }
            }
        }

        return [$allRelatedModels, $relatedModelClass];
    }

    /**
     * Collect nested related models following a relation path.
     *
     * @param Collection<int|string, mixed> $collection
     * @return array{0: array<int, mixed>, 1: class-string<Model>|null, 2: string} [models, modelClass, remainingPath]
     */
    protected function collectNestedRelatedModels(Collection $collection, string $nestedRelation): array
    {
        $relations = explode('.', $nestedRelation);
        $primaryRelation = array_shift($relations) ?? '';
        $remainingPath = implode('.', $relations);

        if (!$this->areRelationsLoaded($collection, [$primaryRelation])) {
            $this->loadRelation($collection, $primaryRelation);
        }

        [$allRelatedModels, $relatedModelClass] = $this->collectRelatedModels($collection, $primaryRelation);

        return [$allRelatedModels, $relatedModelClass, $remainingPath];
    }

    /**
     * Check if relations are loaded on all models in collection.
     *
     * @param Collection<int|string, mixed> $collection
     * @param array<int, string> $relations
     */
    protected function areRelationsLoaded(Collection $collection, array $relations): bool
    {
        foreach ($collection->all() as $model) {
            if (!is_object($model) || !method_exists($model, 'relationLoaded')) {
                return false;
            }

            foreach ($relations as $relation) {
                if (!$model->relationLoaded($relation)) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Parse relation string to extract relation name and column selection.
     *
     * @return array{0: string, 1: array<int, string>} [relationName, columns]
     */
    protected function parseRelationWithColumns(string $relation): array
    {
        if (!str_contains($relation, ':')) {
            return [$relation, []];
        }

        [$relationName, $columnsString] = explode(':', $relation, 2);
        $columns = array_map('trim', explode(',', $columnsString));

        return [$relationName, $columns];
    }

    /**
     * Parse nested relation with column selection.
     *
     * @return array{0: array<int, string>, 1: array<int, string>} [relations, finalColumns]
     */
    protected function parseNestedRelationWithColumns(string $nestedRelation): array
    {
        $parts = explode('.', $nestedRelation);
        $relations = [];
        $finalColumns = [];

        foreach ($parts as $index => $part) {
            [$relationName, $columns] = $this->parseRelationWithColumns($part);
            $relations[] = $relationName;

            if ($index === count($parts) - 1 && !empty($columns)) {
                $finalColumns = $columns;
            }
        }

        return [$relations, $finalColumns];
    }

    /**
     * Apply column selection constraint to a query.
     *
     * @param array<int, string> $columns
     */
    protected function createColumnConstraint(?callable $existingConstraint, array $columns): callable
    {
        return function ($query) use ($existingConstraint, $columns) {
            if (!empty($columns)) {
                $query->select($columns);
            }

            if ($existingConstraint !== null) {
                $existingConstraint($query);
            }
        };
    }

    /**
     * Ensure foreign key is included in query selection.
     */
    protected function ensureForeignKeyInSelection(mixed $query, string $foreignKey): void
    {
        if (isset($query->fields) && $query->fields !== ['*']) {
            $hasKey = false;
            foreach ($query->fields as $field) {
                $plainField = str_replace(['`', '"'], '', (string) $field);
                if (strpos($plainField, '.') !== false) {
                    $parts = explode('.', $plainField);
                    $plainField = end($parts);
                }
                if ($plainField === $foreignKey) {
                    $hasKey = true;
                    break;
                }
            }

            if (!$hasKey) {
                $query->fields[] = $foreignKey;
            }
        }
    }

    /**
     * Ensure required keys are included in query selection.
     */
    protected function ensureRequiredKeysInSelection(mixed $query, string $foreignKey, string $primaryKey): void
    {
        if (isset($query->fields) && $query->fields === ['*']) {
            return;
        }

        $keysToEnsure = [$foreignKey, $primaryKey];

        foreach ($keysToEnsure as $key) {
            $hasKey = false;
            foreach ($query->fields as $field) {
                $plainField = str_replace(['`', '"'], '', (string) $field);
                if (strpos($plainField, '.') !== false) {
                    $parts = explode('.', $plainField);
                    $plainField = end($parts);
                }
                if ($plainField === $key) {
                    $hasKey = true;
                    break;
                }
            }

            if (!$hasKey) {
                $query->fields[] = $key;
            }
        }
    }
}