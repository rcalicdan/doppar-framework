<?php

namespace Phaseolies\Database\Entity\Query;

use Phaseolies\Database\Entity\Model;

trait InteractsWithNestedRelations
{
    /**
     * Handle nested relationship exists checks (e.g., 'comments.reply').
     */
    private function handleNestedRelationshipExists(string $nestedRelation, ?callable $callback, string $boolean, string $type): self
    {
        $relations = explode('.', $nestedRelation);
        $model = $this->getModel();

        $bindings = [];

        $subquery = $this->buildNestedRelationshipExistsSubquery($model, $relations, $callback, $bindings);
        $subquery .= ' LIMIT 1';

        $this->conditions[] = [
            'type' => $type,
            'subquery' => $subquery,
            'bindings' => $bindings,
            'boolean' => $boolean
        ];

        return $this;
    }


    /**
     * Build a nested relationship EXISTS subquery.
     * 
     * @param array<int, string> $relations
     * @param array<int, mixed> &$bindings
     */
    private function buildNestedRelationshipExistsSubquery(Model $model, array $relations, ?callable $callback, array &$bindings): string
    {
        $quote = fn($identifier) => $this->quoteIdentifier($identifier);

        $subqueryParts = [];
        $currentModel = $model;
        $previousTable = $this->table;
        $previousKey = null;
        $relatedTables = [];

        foreach ($relations as $index => $relation) {
            if (!method_exists($currentModel, $relation)) {
                throw new \BadMethodCallException("Relationship {$relation} does not exist on model " . get_class($currentModel));
            }

            $currentModel->$relation();
            
            $relationType = $currentModel->getLastRelationType();
            $relatedModelClass = $currentModel->getLastRelatedModel();
            $foreignKey = $currentModel->getLastForeignKey();
            $localKey = $currentModel->getLastLocalKey();

            /** @var Model $relatedModelInstance */
            $relatedModelInstance = new $relatedModelClass();
            $relatedTable = $relatedModelInstance->getTable();
            $relatedPrimaryKey = $relatedModelInstance->getKeyName();
            $relatedTables[] = [$relatedModelClass, $relatedTable];

            if ($index === 0) {
                if ($relationType === 'bindToMany') {
                    $pivotTable = $currentModel->getLastPivotTable();
                    $relatedKey = $currentModel->getLastRelatedKey();

                    $subqueryParts['from'] = $quote((string) $pivotTable);
                    $subqueryParts['joins'][] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($relatedPrimaryKey)} = {$quote((string) $pivotTable)}.{$quote((string) $relatedKey)}";
                    $subqueryParts['where'] = "{$quote((string) $pivotTable)}.{$quote($foreignKey)} = {$quote($this->table)}.{$quote($localKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                } else {
                    $subqueryParts['from'] = $quote($relatedTable);
                    $subqueryParts['where'] = "{$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($this->table)}.{$quote($localKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                }
            } else {
                if ($relationType === 'bindToMany') {
                    $pivotTable = $currentModel->getLastPivotTable();
                    $relatedKey = $currentModel->getLastRelatedKey();

                    $subqueryParts['joins'][] = "INNER JOIN {$quote((string) $pivotTable)} ON {$quote((string) $pivotTable)}.{$quote($foreignKey)} = {$quote($previousTable)}.{$quote((string) $previousKey)}";
                    $subqueryParts['joins'][] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($relatedPrimaryKey)} = {$quote((string) $pivotTable)}.{$quote((string) $relatedKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                } else {
                    $subqueryParts['joins'][] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($previousTable)}.{$quote((string) $previousKey)}";

                    $previousTable = $relatedTable;
                    $previousKey = $relatedPrimaryKey;
                }
            }

            $currentModel = $relatedModelInstance;
        }

        $subquery = "SELECT 1 FROM {$subqueryParts['from']}";

        if (!empty($subqueryParts['joins'])) {
            $subquery .= ' ' . implode(' ', $subqueryParts['joins']);
        }

        $subquery .= " WHERE {$subqueryParts['where']}";

        array_pop($relatedTables);
        foreach ($relatedTables as [$relatedModel, $relatedTable]) {
            $subquery .= $this->relatedSoftDeleteClause($relatedModel, $relatedTable);
        }

        $subQueryBuilder = $currentModel::query($this->connectionName);

        if ($callback && $previousTable) {
            $callback($subQueryBuilder);

            $subquery .= $this->compileCallbackConditions($subQueryBuilder, $previousTable, $bindings);
        }

        if (($softDeleteConstraint = $subQueryBuilder->compileSoftDeleteConstraint($previousTable)) !== null) {
            $subquery .= " AND {$softDeleteConstraint}";
        }

        return $subquery;
    }

    /**
     * Handle nested whereLinked (e.g., 'comments.reply').
     */
    protected function whereLinkedNested(string $nestedRelation, string $column, mixed $operator, mixed $value): self
    {
        $relations = explode('.', $nestedRelation);
        $model = $this->getModel();

        $subquery = $this->buildNestedRelationshipSubquery($model, $relations, $column, $operator, $value);

        $this->conditions[] = [
            'type' => 'EXISTS',
            'subquery' => $subquery,
            'bindings' => [],
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Build a nested relationship subquery for whereLinked.
     *
     * @param array<int, string> $relations
     */
    protected function buildNestedRelationshipSubquery(Model $model, array $relations, string $column, mixed $operator, mixed $value): string
    {
        $quote = fn($identifier) => $this->quoteIdentifier($identifier);
        $escapeValue = fn($val) => $this->escapeValue($val);

        $currentModel = $model;
        $rootAlias = $this->table . '_sub';
        $currentTable = $rootAlias;
        $joins = [];
        $lastForeignKey = null;
        $lastLocalKey = null;
        $softDeleteClauses = [];

        foreach ($relations as $index => $relation) {
            if (!method_exists($currentModel, $relation)) {
                throw new \BadMethodCallException("Relationship {$relation} does not exist on model " . get_class($currentModel));
            }

            $currentModel->$relation();
            
            $relationType = $currentModel->getLastRelationType();
            $relatedModelClass = $currentModel->getLastRelatedModel();
            $foreignKey = $currentModel->getLastForeignKey();
            $localKey = $currentModel->getLastLocalKey();

            /** @var Model */
            $relatedModelInstance = new $relatedModelClass();
            $relatedTable = $relatedModelInstance->getTable();
            $relatedPrimaryKey = $relatedModelInstance->getKeyName();
            $softDeleteClauses[] = $this->relatedSoftDeleteClause($relatedModelClass, $relatedTable);

            if ($relationType === 'bindToMany') {
                $pivotTable = $currentModel->getLastPivotTable();
                $relatedKey = $currentModel->getLastRelatedKey();

                if ($index === 0) {
                    $joins[] = "INNER JOIN {$quote((string) $pivotTable)} ON {$quote((string) $pivotTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote($localKey)}";
                } else {
                    $joins[] = "INNER JOIN {$quote((string) $pivotTable)} ON {$quote((string) $pivotTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote((string) $lastLocalKey)}";
                }

                $joins[] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($relatedPrimaryKey)} = {$quote((string) $pivotTable)}.{$quote((string) $relatedKey)}";
            } else {
                if ($index === 0) {
                    $joins[] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote($localKey)}";
                } else {
                    $joins[] = "INNER JOIN {$quote($relatedTable)} ON {$quote($relatedTable)}.{$quote($foreignKey)} = {$quote($currentTable)}.{$quote((string) $lastLocalKey)}";
                }
            }

            $currentModel = $relatedModelInstance;
            $currentTable = $relatedTable;
            $lastForeignKey = $foreignKey;
            $lastLocalKey = $relatedPrimaryKey;
        }

        $subquery = "SELECT 1 FROM {$quote($this->table)} AS {$quote($rootAlias)}";
        $subquery .= ' ' . implode(' ', $joins);

        if (!empty($relations)) {
            $firstRelation = $relations[0];
            $model->$firstRelation();
            $firstLocalKey = $model->getLastLocalKey();

            $subquery .= " WHERE {$quote($rootAlias)}.{$quote($firstLocalKey)} = {$quote($this->table)}.{$quote($firstLocalKey)}";
        }

        $finalTable = $currentTable;
        $columnQualified = strpos($column, '.') === false
            ? "{$quote($finalTable)}.{$quote($column)}"
            : $quote($column);

        $subquery .= $this->buildConditionClause($columnQualified, (string) $operator, $value, $escapeValue);
        $subquery .= implode('', $softDeleteClauses);
        $subquery .= ' LIMIT 1';

        return $subquery;
    }
}