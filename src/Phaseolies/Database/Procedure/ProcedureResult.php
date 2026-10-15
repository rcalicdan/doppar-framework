<?php

namespace Phaseolies\Database\Procedure;

use Traversable;
use Phaseolies\Support\Collection;
use ArrayIterator;
use ArrayAccess;
use JsonSerializable;

/**
 * @implements ArrayAccess<string, mixed>
 */
class ProcedureResult implements ArrayAccess, JsonSerializable
{
    /**
     * The stored procedure results.
     * This is a multidimensional array where each element represents a result set,
     * which itself is an array of rows (associative arrays).
     *
     * @var array<int, array<int, array<string, mixed>>>
     */
    protected array $results = [];

    /**
     * Current result set being accessed.
     */
    protected int $currentSet = 0;

    /**
     * Current row being accessed.
     */
    protected int $currentRow = 0;

    /**
     * Initialize the ProcedureResult with an array of result sets.
     *
     * @param array<int, array<int, array<string, mixed>>> $results
     */
    public function __construct(array $results)
    {
        $this->results = $results;
    }

    /**
     * Determine if an offset exists in the current row of the current result set.
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->getCurrentData()[(string) $offset]);
    }

    /**
     * Retrieve a value by offset from the current row of the current result set.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->getCurrentData()[(string) $offset] ?? null;
    }

    /**
     * Set a value by offset on the current row of the current result set.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $currentData = &$this->getCurrentDataReference();

        if ($offset === null) {
            $currentData[] = $value;
        } else {
            $currentData[(string) $offset] = $value;
        }
    }

    /**
     * Unset a value by offset on the current row of the current result set.
     */
    public function offsetUnset(mixed $offset): void
    {
        $currentData = &$this->getCurrentDataReference();

        unset($currentData[(string) $offset]);
    }

    /**
     * Get a property from the current row.
     */
    public function __get(string $name): mixed
    {
        return $this->getCurrentData()[$name] ?? null;
    }

    /**
     * Set a property on the current row.
     */
    public function __set(string $name, mixed $value): void
    {
        $currentData = &$this->getCurrentDataReference();

        $currentData[$name] = $value;
    }

    /**
     * Check if a property is set on the current row.
     */
    public function __isset(string $name): bool
    {
        return isset($this->getCurrentData()[$name]);
    }

    /**
     * Unset a property on the current row.
     */
    public function __unset(string $name): void
    {
        $currentData = &$this->getCurrentDataReference();

        unset($currentData[$name]);
    }

    /**
     * Get an iterator for the current row's data.
     *
     * @return Traversable<string, mixed>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getCurrentData());
    }

    /**
     * Specify data which should be serialized to JSON.
     */
    public function jsonSerialize(): mixed
    {
        return $this->getCurrentData();
    }

    /**
     * Retrieve all rows from the first result set.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function all(): Collection
    {
        return new Collection('array', $this->results[0] ?? []);
    }

    /**
     * Retrieve the first row from the first result set.
     *
     * @return Collection<string, mixed>|null
     */
    public function first(): ?Collection
    {
        $firstRow = $this->results[0][0] ?? null;

        return $firstRow !== null ? new Collection('array', $firstRow) : null;
    }

    /**
     * Retrieve the last row from the first result set.
     *
     * @return Collection<string, mixed>|null
     */
    public function last(): ?Collection
    {
        if (empty($this->results[0])) {
            return null;
        }

        $lastRow = end($this->results[0]);
        reset($this->results[0]);

        return $lastRow ? new Collection('array', $lastRow) : null;
    }

    /**
     * Retrieve the last row from a specified result set.
     *
     * @return Collection<string, mixed>|null
     */
    public function lastSet(int $setIndex): ?Collection
    {
        if (!isset($this->results[$setIndex]) || empty($this->results[$setIndex])) {
            return null;
        }

        $lastRow = end($this->results[$setIndex]);
        reset($this->results[$setIndex]);

        return $lastRow ? new Collection('array', $lastRow) : null;
    }

    /**
     * Retrieve a specific result set by index.
     *
     * @return array<int, array<string, mixed>>
     */
    public function resultSet(int $index): array
    {
        return $this->results[$index] ?? [];
    }

    /**
     * Get the current row data from the current result set.
     *
     * @return array<string, mixed>
     */
    protected function getCurrentData(): array
    {
        return $this->results[$this->currentSet][$this->currentRow] ?? [];
    }

    /**
     * Get a reference to the current row data for modification.
     *
     * This method ensures that the current result set and row exist,
     * initializing them as empty arrays if necessary.
     *
     * @return array<string, mixed>
     */
    protected function &getCurrentDataReference(): array
    {
        if (!isset($this->results[$this->currentSet])) {
            $this->results[$this->currentSet] = [];
        }

        if (!isset($this->results[$this->currentSet][$this->currentRow])) {
            $this->results[$this->currentSet][$this->currentRow] = [];
        }

        return $this->results[$this->currentSet][$this->currentRow];
    }
}
