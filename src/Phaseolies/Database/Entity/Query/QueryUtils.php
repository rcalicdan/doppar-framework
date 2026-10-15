<?php

namespace Phaseolies\Database\Entity\Query;

use RuntimeException;
use Phaseolies\Support\Collection;
use Phaseolies\Database\Entity\Builder;

trait QueryUtils
{
    /**
     * Execute a query and get the result as a key-value dictionary.
     *
     * @return Collection<array-key, mixed>
     */
    public function toDictionary(string $keyColumn, string $valueColumn): Collection
    {
        $results = $this->select([$keyColumn, $valueColumn])->get();
        $dictionary = [];

        foreach ($results as $result) {
            $dictionary[$result->$keyColumn] = $result->$valueColumn;
        }

        return new Collection('array', $dictionary);
    }

    /**
     * Execute a query and get the difference between two columns.
     *
     * @return Collection<array-key, mixed>
     */
    public function toDiff(string $column1, string $column2, string $alias = 'difference'): Collection
    {
        return $this->select("{$column1} - {$column2} as {$alias}")->get();
    }

    /**
     * Execute a query and get the result as a nested tree structure.
     *
     * @return Collection<array-key, mixed>
     */
    public function toTree(string $primaryKey, string $parentColumn, string $index = 'children'): Collection
    {
        $items = $this->get();
        $grouped = [];
        $visited = [];

        foreach ($items as $item) {
            $parentId = $item->$parentColumn;
            $parentId = $parentId === null ? '' : $parentId;
            if (!isset($grouped[$parentId])) {
                $grouped[$parentId] = [];
            }
            $grouped[$parentId][] = $item;
        }

        $buildTree = function ($parentId = null) use (&$buildTree, &$visited, $grouped, $index, $primaryKey) {
            $branch = [];
            $parentId = $parentId === null ? '' : $parentId;

            if (isset($grouped[$parentId])) {
                foreach ($grouped[$parentId] as $item) {
                    $itemId = $item->$primaryKey;

                    if (isset($visited[$itemId])) {
                        throw new \RuntimeException("Circular reference detected in tree structure for item ID: {$itemId}");
                    }

                    $visited[$itemId] = true;
                    $children = $buildTree($itemId);
                    unset($visited[$itemId]);

                    if (!empty($children)) {
                        $item->setRelation($index, new Collection($this->modelClass, $children));
                    }
                    $branch[] = $item;
                }
            }

            return $branch;
        };

        $treeArray = $buildTree();

        return new Collection($this->modelClass, $treeArray);
    }

    /**
     * Execute a query and get the ratio between two columns.
     *
     * @return Collection<array-key, mixed>
     */
    public function toRatio(string $numerator, string $denominator, string $alias = 'ratio', int $precision = 2): Collection
    {
        return $this->selectRaw("ROUND({$numerator} / NULLIF({$denominator}, 0), {$precision}) as {$alias}")->get();
    }

    /**
     * Execute a query with a random sample.
     */
    public function random(int $limit): Builder
    {
        return $this->orderByRaw($this->rand())->limit($limit);
    }

    /**
     * Search a JSON column.
     */
    public function whereJson(string $column, string $path, mixed $value, string $boolean = 'AND'): self
    {
        [$expression, $bindings] = $this->jsonContains($column, $path, $value);

        return $this->whereRaw($expression, $bindings, $boolean);
    }

    /**
     * Execute a query with a case-insensitive pattern matching filter.
     */
    public function iLike(string $column, string $pattern): Builder
    {
        return $this->whereRaw("LOWER({$column}) LIKE LOWER(?)", [$pattern]);
    }

    /**
     * Execute a query with a custom transformation function.
     */
    public function transformBy(callable $callback, string $alias = 'transformed'): self
    {
        $transformationBuilder = new self($this->pdo, $this->table, $this->modelClass, $this->rowPerPage);

        $expression = $callback($transformationBuilder);

        if (!is_string($expression)) {
            $expression = $transformationBuilder->toSql();
        }

        if (str_contains($expression, ' ')) {
            $expression = "({$expression})";
        }

        $this->selectRaw("{$expression} as {$alias}");

        return $this;
    }

    /**
     * Execute a query with a pattern matching filter.
     */
    public function wherePattern(string $column, string $pattern, string $type = 'LIKE'): Builder
    {
        return $this->whereRaw("{$column} {$type} ?", [$pattern]);
    }

    /**
     * Execute a query with a first/last value in window with partitioning.
     */
    public function firstLastInWindow(
        string $column,
        string $orderColumn,
        string $partitionColumn,
        bool $first = true,
        string $alias = 'window_value'
    ): Builder {
        $function = $first ? 'FIRST_VALUE' : 'LAST_VALUE';

        return $this->selectRaw(
            "{$function}({$column}) OVER (
            PARTITION BY {$partitionColumn} 
            ORDER BY {$orderColumn} 
            ROWS BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING
        ) as {$alias}"
        );
    }

    /**
     * Execute a query with a running difference calculation.
     */
    public function movingDifference(string $column, string $orderColumn, string $alias = 'running_diff'): Builder
    {
        return $this->selectRaw(
            "{$column} - LAG({$column}, 1, 0) OVER (ORDER BY {$orderColumn}) as {$alias}"
        );
    }

    /**
     * Execute a query with a moving average calculation.
     */
    public function movingAverage(string $column, int $windowSize, string $orderColumn, string $alias = 'moving_avg'): Builder
    {
        return $this->selectRaw(
            "AVG({$column}) OVER (ORDER BY {$orderColumn} ROWS BETWEEN ? PRECEDING AND CURRENT ROW) as {$alias}",
            [$windowSize - 1]
        );
    }
}