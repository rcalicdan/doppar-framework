<?php

namespace Phaseolies\Support;

use Traversable;
use Ramsey\Collection\Collection as RamseyCollection;
use Phaseolies\Database\Entity\Model;
use IteratorAggregate;
use ArrayIterator;
use ArrayAccess;
use JsonSerializable;

/**
 * @template TKey of array-key
 * @template TValue
 * @extends RamseyCollection<TValue>
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 * @phpstan-consistent-constructor
 */
class Collection extends RamseyCollection implements IteratorAggregate, ArrayAccess, JsonSerializable
{
    /**
     * The internal data array.
     *
     * @var array<TKey, TValue>
     */
    protected array $data = [];

    /**
     * The model class name associated with the collection.
     */
    protected ?string $model = null;

    /**
     * Methods that can be used as `$collection->method->property` or `$collection->method->call()`.
     *
     * @var array<int, string>
     */
    protected static array $proxies = [
        'filter',
        'reject',
        'sum',
        'avg',
        'min',
        'max',
        'sortBy',
        'sortByDesc',
        'groupBy',
        'keyBy',
        'unique',
        'every',
        'some',
        'flatMap',
    ];

    /**
     * Create a new collection instance.
     *
     * @param array<TKey, TValue>|null $data
     */
    public function __construct(string $model, ?array $data = [])
    {
        $this->model = $model;
        $this->data = $data ?? [];
    }

    /**
     * Access collection data properties directly.
     */
    public function __get(string $name): mixed
    {
        if ($name === 'map' || $name === 'each') {
            return new HigherOrderCollectionProxy($this, $name);
        }

        if (in_array($name, static::$proxies, true) && !array_key_exists($name, $this->data)) {
            return new HigherOrderCollectionProxy($this, $name);
        }

        return $this->data[$name] ?? null;
    }

    /**
     * Check if a property exists in the collection data.
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    /**
     * Determines if the specified offset exists in the data array.
     *
     * @param TKey $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Returns the value at the specified offset.
     *
     * @param TKey $offset
     * @return TValue
     */
    public function offsetGet(mixed $offset): mixed
    {
        /** @var TValue */
        return $this->data[$offset] ?? null;
    }

    /**
     * Sets a value at the specified offset in the data array.
     *
     * @param TKey|null $offset
     * @param TValue $value
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $data = $this->data;
            $data[] = $value;
            /** @var array<TKey, TValue> $data */
            $this->data = $data;

            return;
        }

        /** @var TKey $offset */
        /** @var TValue $value */
        $this->data[$offset] = $value;
    }

    /**
     * Removes the value at the specified offset from the data array.
     *
     * @param TKey $offset
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Required for looping data.
     *
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->data);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array<TKey, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Get all data in the collection.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get the first item, or the first item that passes a test.
     *
     * @template TDefault
     * @param (callable(TValue, TKey): bool)|null $callback
     * @param TDefault|(\Closure(): TDefault) $default
     * @return TValue|TDefault
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        foreach ($this->data as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                return $item;
            }
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    /**
     * Get the last item, or the last item that passes a test.
     *
     * @template TDefault
     * @param (callable(TValue, TKey): bool)|null $callback
     * @param TDefault|(\Closure(): TDefault) $default
     * @return TValue|TDefault
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        foreach (array_reverse($this->data, true) as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                return $item;
            }
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    /**
     * Convert the collection to an array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray(): array
    {
        return array_map(fn($item) => $item instanceof Model ? $item->toArray() : $item, $this->data);
    }

    /**
     * Apply a callback to each item in the collection.
     *
     * @template TMapValue
     * @param callable(TValue, TKey): TMapValue $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback): static
    {
        $mappedItems = [];

        foreach ($this->data as $key => $item) {
            $mappedItems[$key] = $callback($item, $key);
        }

        return new static((string) $this->model, $mappedItems);
    }

    /**
     * Filter the collection using the given callback. Without a callback, every
     * item that is "falsy" (null, false, 0, '', []) is removed.
     *
     * @param (callable(TValue, TKey): bool)|null $callback
     * @return static<TKey, TValue>
     */
    public function filter(?callable $callback = null): static
    {
        $filteredItems = [];

        foreach ($this->data as $key => $item) {
            if ($callback === null ? (bool) $item : $callback($item, $key)) {
                $filteredItems[$key] = $item;
            }
        }

        return new static((string) $this->model, $filteredItems);
    }

    /**
     * Execute a callback over each item.
     *
     * @param callable(TValue, TKey): mixed $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this->data as $key => $item) {
            if ($callback($item, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Add an item to the end of the collection.
     *
     * @param TValue $item
     * @return $this
     */
    public function push(mixed $item): static
    {
        $data = $this->data;
        $data[] = $item;
        /** @var array<TKey, TValue> $data */
        $this->data = $data;

        return $this;
    }

    /**
     * Flatten a multi-dimensional collection into a single level.
     *
     * @return static<int, mixed>
     */
    public function flatten(int $depth = PHP_INT_MAX): static
    {
        $result = [];
        $stack = [];

        foreach (array_reverse($this->data) as $item) {
            $stack[] = ['item' => $item, 'depth' => 0];
        }

        while (!empty($stack)) {
            $current = array_pop($stack);
            $item = $current['item'];
            $currentDepth = $current['depth'];

            if ($currentDepth >= $depth) {
                $result[] = $item;
                continue;
            }

            if (is_array($item)) {
                foreach (array_reverse(array_values($item)) as $subItem) {
                    $stack[] = [
                        'item' => $subItem,
                        'depth' => $currentDepth + 1,
                    ];
                }
            } elseif ($item instanceof Collection) {
                foreach (array_reverse($item->all()) as $subItem) {
                    $stack[] = [
                        'item' => $subItem,
                        'depth' => $currentDepth + 1,
                    ];
                }
            } else {
                $result[] = $item;
            }
        }

        return new static((string) $this->model, $result);
    }

    /**
     * Get the values.
     *
     * @return static<int, TValue>
     */
    public function values(): static
    {
        return new static((string) $this->model, array_values($this->data));
    }

    /**
     * Return a new collection with unique items.
     *
     * @return static<TKey, TValue>
     */
    public function unique(?string $key = null, bool $strict = false): static
    {
        $uniqueItems = [];
        $exists = [];

        foreach ($this->data as $origKey => $item) {
            $value = $key !== null ? $this->valueFrom($item, $key) : $item;

            $serialized = $strict ? serialize($value) : (is_scalar($value) ? (string) $value : serialize($value));

            if (!isset($exists[$serialized])) {
                $exists[$serialized] = true;
                $uniqueItems[$origKey] = $item;
            }
        }

        return new static((string) $this->model, $uniqueItems);
    }

    /**
     * Pluck an array of values from a given key.
     *
     * @return static<array-key, mixed>
     */
    public function pluck(string $value, ?string $key = null): static
    {
        $results = [];

        foreach ($this->data as $item) {
            $itemValue = $this->valueFrom($item, $value);
            $itemKey = $key ? $this->valueFrom($item, $key) : null;

            if ($key === null) {
                $results[] = $itemValue;
            } elseif ($itemKey !== null && (is_string($itemKey) || is_int($itemKey))) {
                $results[$itemKey] = $itemValue;
            }
        }

        return new static((string) $this->model, $results);
    }

    /**
     * Determine if the collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    /**
     * Determine if the collection is not empty.
     */
    public function isNotEmpty(): bool
    {
        return !empty($this->data);
    }

    /**
     * Output or return memory usage stats related to the current collection.
     *
     * @return string|array<string, mixed>
     */
    public function withMemoryUsage(bool $asString = true): string|array
    {
        $usage = memory_get_usage(true);
        $peak  = memory_get_peak_usage(true);

        $data = [
            'current_usage_bytes' => $usage,
            'peak_usage_bytes'    => $peak,
            'current_usage_mb'    => round($usage / 1024 / 1024, 2) . ' MB',
            'peak_usage_mb'       => round($peak / 1024 / 1024, 2) . ' MB',
        ];

        if ($asString) {
            return sprintf(
                "Memory usage: %s, Peak: %s",
                $data['current_usage_mb'],
                $data['peak_usage_mb']
            );
        }

        return $data;
    }

    /**
     * Map the collection and group the results by the given key.
     *
     * @param (callable(TValue, TKey): array-key)|string $groupBy
     * @param (callable(TValue, TKey): mixed)|null $mapCallback
     * @return array<string, array<int, mixed>>
     */
    public function mapAsGroup(callable|string $groupBy, ?callable $mapCallback = null): array
    {
        $results = [];
        $groupResolver = $this->buildKeyResolver($groupBy);

        foreach ($this->data as $key => $item) {
            $groupKey = $groupResolver($item, $key);
            $mappedItem = $mapCallback ? $mapCallback($item, $key) : $item;

            if ($groupKey !== null) {
                $groupKey = (string) $groupKey;
                $results[$groupKey][] = $mappedItem;
            }
        }

        return $results;
    }

    /**
     * Map the collection and use the given key as array keys.
     *
     * @param (callable(TValue, TKey): array-key)|string $keyBy
     * @param (callable(TValue, TKey): mixed)|null $mapCallback
     * @return array<string, mixed>
     */
    public function mapAsKey(callable|string $keyBy, ?callable $mapCallback = null): array
    {
        $results = [];
        $keyResolver = $this->buildKeyResolver($keyBy);

        foreach ($this->data as $index => $item) {
            $itemKey = $keyResolver($item, $index);
            $mappedItem = $mapCallback ? $mapCallback($item, $index) : $item;

            if ($itemKey !== null) {
                $itemKey = (string) $itemKey;
                $results[$itemKey] = $mappedItem;
            }
        }

        return $results;
    }

    /**
     * Build a key resolver from various input types.
     */
    protected function buildKeyResolver(mixed $key): callable
    {
        if (!is_string($key) && is_callable($key)) {
            return $key;
        }

        return fn($item) => $this->valueFrom($item, $key);
    }

    /**
     * Read a key from an item. Works on arrays, objects, and ArrayAccess.
     */
    public function valueFrom(mixed $item, string|int $key): mixed
    {
        $found = false;
        $value = $this->readKey($item, $key, $found);

        if ($found || !is_string($key) || !str_contains($key, '.')) {
            return $value;
        }

        $current = $item;

        foreach (explode('.', $key) as $segment) {
            $current = $this->readKey($current, $segment, $found);

            if (!$found) {
                return null;
            }
        }

        return $current;
    }

    /**
     * Read a single key from an item.
     */
    private function readKey(mixed $item, string|int $key, bool &$found = false): mixed
    {
        $found = false;

        if (is_array($item)) {
            if (array_key_exists($key, $item)) {
                $found = true;

                return $item[$key];
            }

            return null;
        }

        if (is_object($item)) {
            if (isset($item->{$key})) {
                $found = true;

                return $item->{$key};
            }

            if ($item instanceof ArrayAccess && isset($item[$key])) {
                $found = true;

                return $item[$key];
            }
        }

        return null;
    }

    /**
     * Group the collection by the given key with mapping capability.
     *
     * @param (callable(TValue, TKey): array-key)|string $groupBy
     * @param (callable(TValue, TKey): mixed)|null $mapCallback
     * @return array<string, array<int, mixed>>
     */
    public function groupBy(callable|string $groupBy, ?callable $mapCallback = null): array
    {
        return $this->mapAsGroup($groupBy, $mapCallback);
    }

    /**
     * Key the collection by the given key with mapping capability.
     *
     * @param (callable(TValue, TKey): array-key)|string $keyBy
     * @param (callable(TValue, TKey): mixed)|null $mapCallback
     * @return array<string, mixed>
     */
    public function keyBy(callable|string $keyBy, ?callable $mapCallback = null): array
    {
        return $this->mapAsKey($keyBy, $mapCallback);
    }

    /**
     * Sort the collection by a given key or callback, or by several of them.
     *
     * @param (callable(TValue, TKey): mixed)|string|array<int, mixed> $callback
     * @return static<TKey, TValue>
     */
    public function sortBy(callable|string|array $callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        if (is_array($callback) && !is_callable($callback)) {
            return $this->sortByMany($callback, $options, $descending);
        }

        $results = [];
        $resolver = $this->buildKeyResolver($callback);

        foreach ($this->data as $key => $item) {
            $results[$key] = $resolver($item, $key);
        }

        if ($descending) {
            arsort($results, $options);
        } else {
            asort($results, $options);
        }

        $sortedData = [];
        foreach (array_keys($results) as $key) {
            $sortedData[$key] = $this->data[$key];
        }

        return new static((string) $this->model, $sortedData);
    }

    /**
     * Sort by several criteria; each is a key, a callback, or [key, 'asc'|'desc'].
     *
     * @param array<int, mixed> $criteria
     * @return static<TKey, TValue>
     */
    protected function sortByMany(array $criteria, int $options, bool $descending): static
    {
        $resolvers = [];

        foreach ($criteria as $criterion) {
            $direction = $descending;

            if (is_array($criterion) && !is_callable($criterion)) {
                [$criterion, $named] = array_pad(array_values($criterion), 2, null);
                $direction = $named === null ? $descending : (is_bool($named) ? $named : strtolower((string) $named) === 'desc');
            }

            $resolvers[] = [$this->buildKeyResolver($criterion), $direction];
        }

        $keyed = [];
        foreach ($this->data as $key => $item) {
            $keyed[] = [$key, $item, array_map(fn($r) => $r[0]($item, $key), $resolvers)];
        }

        usort($keyed, function ($a, $b) use ($resolvers, $options) {
            foreach ($resolvers as $i => [, $desc]) {
                $result = $this->compareValues($a[2][$i], $b[2][$i], $options);

                if ($result !== 0) {
                    return $desc ? -$result : $result;
                }
            }

            return 0;
        });

        $sorted = [];
        foreach ($keyed as $entry) {
            $sorted[$entry[0]] = $entry[1];
        }

        return new static((string) $this->model, $sorted);
    }

    /**
     * Compare two values according to the given sort flags.
     */
    protected function compareValues(mixed $a, mixed $b, int $options): int
    {
        $flags = $options & ~SORT_FLAG_CASE;
        $caseless = ($options & SORT_FLAG_CASE) === SORT_FLAG_CASE;

        return match ($flags) {
            SORT_STRING => $caseless ? strcasecmp((string) $a, (string) $b) <=> 0 : strcmp((string) $a, (string) $b) <=> 0,
            SORT_NATURAL => $caseless ? strnatcasecmp((string) $a, (string) $b) : strnatcmp((string) $a, (string) $b),
            SORT_NUMERIC => (float) $a <=> (float) $b,
            default => $a <=> $b,
        };
    }

    /**
     * Sort the collection in descending order by a given key or callback.
     *
     * @param (callable(TValue, TKey): mixed)|string $callback
     * @return static<TKey, TValue>
     */
    public function sortByDesc(callable|string $callback, int $options = SORT_REGULAR): static
    {
        return $this->sortBy($callback, $options, true);
    }

    /**
     * Transform each item in the collection into one or more key-value pairs grouped by keys.
     *
     * @param callable(TValue, TKey): array<array-key, mixed> $callback
     * @return array<string, array<int, mixed>>
     */
    public function mapToGroups(callable $callback): array
    {
        $results = [];

        foreach ($this->data as $key => $item) {
            $result = $callback($item, $key);

            foreach ($result as $groupKey => $value) {
                $results[$groupKey][] = $value;
            }
        }

        return $results;
    }

    /**
     * Transform the collection into a flat associative array with custom keys.
     *
     * @param callable(TValue, TKey): array<array-key, mixed> $callback
     * @return array<string, mixed>
     */
    public function mapWithKeys(callable $callback): array
    {
        $results = [];

        foreach ($this->data as $key => $item) {
            $result = $callback($item, $key);

            foreach ($result as $mapKey => $mapValue) {
                $results[$mapKey] = $mapValue;
            }
        }

        return $results;
    }

    /**
     * Convert the collection to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return (string) json_encode($this->toArray(), $options);
    }

    /**
     * Take the first or last {$limit} items from the collection.
     *
     * @return static<TKey, TValue>
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->takeLast(abs($limit));
        }

        return new static((string) $this->model, array_slice($this->data, 0, $limit, true));
    }

    /**
     * Take the last {$limit} items from the collection.
     *
     * @return static<TKey, TValue>
     */
    public function takeLast(int $limit): static
    {
        if ($limit <= 0) {
            return new static((string) $this->model, []);
        }

        return new static((string) $this->model, array_slice($this->data, -$limit, null, true));
    }

    /**
     * Ensure the model is preserved when the collection is serialized by cache.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        $parent = [];

        if (method_exists(get_parent_class($this) ?: '', '__serialize')) {
            $parent = parent::__serialize();
        }

        $payload = [
            '__ph_model' => $this->model,
            '__ph_data' => $this->data,
        ];

        return array_merge($parent, $payload);
    }

    /**
     * Restore the model and data when the collection is unserialized by cache.
     *
     * @param array<string, mixed> $data
     */
    public function __unserialize(array $data): void
    {
        $this->model = isset($data['__ph_model']) && is_string($data['__ph_model']) ? $data['__ph_model'] : null;
        $this->data = isset($data['__ph_data']) && is_array($data['__ph_data']) ? $data['__ph_data'] : [];

        if (method_exists(get_parent_class($this) ?: '', '__unserialize')) {
            unset($data['__ph_model'], $data['__ph_data']);
            parent::__unserialize($data);
        }
    }

    /**
     * The type of the items, as the parent collection reports it.
     */
    public function getType(): string
    {
        return $this->model ?: 'mixed';
    }

    /**
     * Combine this collection with other collections or arrays into a new one.
     *
     * @param iterable<mixed, mixed> ...$collections
     * @return static<array-key, mixed>
     */
    public function merge(iterable ...$collections): static
    {
        $merged = [$this->data];

        foreach ($collections as $collection) {
            $merged[] = $collection instanceof self
                ? $collection->all()
                : (is_array($collection) ? $collection : iterator_to_array($collection));
        }

        return new static((string) $this->model, array_merge(...$merged));
    }

    /**
     * Get the model class name associated to this collection.
     */
    public function getModel(): ?string
    {
        return $this->model;
    }

    /**
     * Split collection into chunks of given size.
     *
     * @return static<int, array<TKey, TValue>>
     */
    public function chunk(int $size): static
    {
        if ($size <= 0) {
            throw new \InvalidArgumentException('Chunk size must be greater than 0');
        }

        /** @var array<int, array<TKey, TValue>> $chunks */
        $chunks = array_chunk($this->data, $size, false);

        return new static((string) $this->model, $chunks);
    }

    /**
     * Split collection into two groups based on condition.
     *
     * @param callable(TValue, TKey): bool $callback
     * @return array{0: static<TKey, TValue>, 1: static<TKey, TValue>}
     */
    public function partition(callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($this->data as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return [
            new static((string) $this->model, $passed),
            new static((string) $this->model, $failed),
        ];
    }

    /**
     * Get items not present in the given items.
     *
     * @return static<TKey, TValue>
     */
    public function diff(mixed $items): static
    {
        $compare = $items instanceof self ? $items->all() : (array) $items;

        $result = [];
        foreach ($this->data as $key => $item) {
            if (!in_array($item, $compare, true)) {
                $result[$key] = $item;
            }
        }

        return new static((string) $this->model, $result);
    }

    /**
     * Get items present in both this collection and given items.
     *
     * @return static<TKey, TValue>
     */
    public function intersect(mixed $items): static
    {
        $compare = $items instanceof self ? $items->all() : (array) $items;

        $result = [];
        foreach ($this->data as $key => $item) {
            if (in_array($item, $compare, true)) {
                $result[$key] = $item;
            }
        }

        return new static((string) $this->model, $result);
    }

    /**
     * Execute a callback over the collection without modifying it.
     *
     * @param callable($this): void $callback
     * @return $this
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Pass the collection to a callback and return the result.
     *
     * @template TPipeReturn
     * @param callable($this): TPipeReturn $callback
     * @return TPipeReturn
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }

    /**
     * Find duplicate items in the collection.
     *
     * @return static<TKey, TValue>
     */
    public function duplicates(?string $key = null): static
    {
        $seen = [];
        $duplicates = [];

        foreach ($this->data as $origKey => $item) {
            $value = $key !== null ? $this->valueFrom($item, $key) : $item;
            $serialized = is_scalar($value) ? (string) $value : serialize($value);

            if (isset($seen[$serialized])) {
                $duplicates[$origKey] = $item;
            } else {
                $seen[$serialized] = true;
            }
        }

        return new static((string) $this->model, $duplicates);
    }

    /**
     * Get the sum of values.
     *
     * @param (callable(TValue, TKey): (int|float))|string|null $callback
     */
    public function sum(callable|string|null $callback = null): int|float
    {
        if ($callback === null) {
            return array_sum($this->data);
        }

        if (is_string($callback)) {
            return $this->pluck($callback)->sum();
        }

        return $this->map($callback)->sum();
    }

    /**
     * Get the average (mean) value.
     *
     * @param (callable(TValue, TKey): (int|float))|string|null $callback
     */
    public function avg(callable|string|null $callback = null): int|float|null
    {
        $count = $this->count();

        if ($count === 0) {
            return null;
        }

        return $this->sum($callback) / $count;
    }

    /**
     * Get the minimum value.
     *
     * @param (callable(TValue, TKey): mixed)|string|null $callback
     */
    public function min(callable|string|null $callback = null): mixed
    {
        if ($this->data === []) {
            return null;
        }

        if ($callback === null) {
            return min($this->data);
        }

        if (is_string($callback)) {
            return $this->pluck($callback)->min();
        }

        return $this->map($callback)->min();
    }

    /**
     * Get the maximum value.
     *
     * @param (callable(TValue, TKey): mixed)|string|null $callback
     */
    public function max(callable|string|null $callback = null): mixed
    {
        if ($this->data === []) {
            return null;
        }

        if ($callback === null) {
            return max($this->data);
        }

        if (is_string($callback)) {
            return $this->pluck($callback)->max();
        }

        return $this->map($callback)->max();
    }

    /**
     * Get a single item that matches the criteria, or throw an exception.
     *
     * @param (callable(TValue, TKey): bool)|null $callback
     * @return TValue
     * @throws \RuntimeException
     */
    public function sole(?callable $callback = null): mixed
    {
        $filtered = $callback ? $this->filter($callback) : $this;
        $count = $filtered->count();

        if ($count === 0) {
            throw new \RuntimeException('No items found matching the criteria');
        }

        if ($count > 1) {
            throw new \RuntimeException("Multiple items found ({$count} items), expected exactly one");
        }

        /** @var TValue */
        return $filtered->first();
    }

    /**
     * Get the items that do not pass the test (the opposite of filter).
     *
     * @param callable(TValue, TKey): bool $callback
     * @return static<TKey, TValue>
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($item, $key) => !$callback($item, $key));
    }

    /**
     * Determine whether every item passes the test. True for an empty collection.
     *
     * @param callable(TValue, TKey): bool $callback
     */
    public function every(callable $callback): bool
    {
        foreach ($this->data as $key => $item) {
            if (!$callback($item, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether at least one item passes the test. False for an empty collection.
     *
     * @param callable(TValue, TKey): bool $callback
     */
    public function some(callable $callback): bool
    {
        foreach ($this->data as $key => $item) {
            if ($callback($item, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reverse the order of the items.
     *
     * @return static<TKey, TValue>
     */
    public function reverse(): static
    {
        return new static((string) $this->model, array_reverse($this->data, true));
    }

    /**
     * Get the keys of the collection as a new collection.
     *
     * @return static<int, TKey>
     */
    public function keys(): static
    {
        return new static((string) $this->model, array_keys($this->data));
    }

    /**
     * Keep only the items with the given keys.
     *
     * @param array<int|string> $keys
     * @return static<TKey, TValue>
     */
    public function only(array $keys): static
    {
        return new static((string) $this->model, array_intersect_key($this->data, array_flip($keys)));
    }

    /**
     * Remove the items with the given keys.
     *
     * @param array<int|string> $keys
     * @return static<TKey, TValue>
     */
    public function except(array $keys): static
    {
        return new static((string) $this->model, array_diff_key($this->data, array_flip($keys)));
    }

    /**
     * Get the first item whose value for a key matches.
     *
     * @return TValue|null
     */
    public function firstWhere(string $key, mixed $operator = null, mixed $value = null): mixed
    {
        $arguments = func_num_args();

        return $this->first(function ($item) use ($key, $operator, $value, $arguments) {
            $retrieved = $this->valueFrom($item, $key);

            return match ($arguments) {
                1 => (bool) $retrieved,
                2 => $this->compareWith($retrieved, '=', $operator),
                default => $this->compareWith($retrieved, (string) $operator, $value),
            };
        });
    }

    /**
     * Compare a retrieved value against an expected value using an operator.
     */
    protected function compareWith(mixed $retrieved, string $operator, mixed $value): bool
    {
        return match ($operator) {
            '=', '==' => $retrieved == $value,
            '===' => $retrieved === $value,
            '!=', '<>' => $retrieved != $value,
            '!==' => $retrieved !== $value,
            '<' => $retrieved < $value,
            '>' => $retrieved > $value,
            '<=' => $retrieved <= $value,
            '>=' => $retrieved >= $value,
            default => throw new \InvalidArgumentException("Unknown comparison operator [{$operator}]."),
        };
    }

    /**
     * Keep the items whose value for a key is one of the given values.
     *
     * @param iterable<mixed> $values
     * @return static<TKey, TValue>
     */
    public function whereIn(string $key, iterable $values, bool $strict = false): static
    {
        $valArray = is_array($values) ? $values : iterator_to_array($values);

        return $this->filter(fn($item) => in_array($this->valueFrom($item, $key), $valArray, $strict));
    }

    /**
     * Keep the items whose value for a key is none of the given values.
     *
     * @param iterable<mixed> $values
     * @return static<TKey, TValue>
     */
    public function whereNotIn(string $key, iterable $values, bool $strict = false): static
    {
        $valArray = is_array($values) ? $values : iterator_to_array($values);

        return $this->filter(fn($item) => !in_array($this->valueFrom($item, $key), $valArray, $strict));
    }

    /**
     * Keep the items that have a value (not null) for a key.
     *
     * @return static<TKey, TValue>
     */
    public function whereNotNull(string $key): static
    {
        return $this->filter(fn($item) => $this->valueFrom($item, $key) !== null);
    }

    /**
     * Keep the items that have no value (null) for a key.
     *
     * @return static<TKey, TValue>
     */
    public function whereNull(string $key): static
    {
        return $this->filter(fn($item) => $this->valueFrom($item, $key) === null);
    }

    /**
     * Count how many times each value occurs.
     *
     * @param ((callable(TValue, TKey): array-key)|string)|null $callback
     * @return array<array-key, int>
     */
    public function countBy(callable|string|null $callback = null): array
    {
        $resolver = $callback === null ? fn($item) => $item : $this->buildKeyResolver($callback);
        $counts = [];

        foreach ($this->data as $key => $item) {
            $value = $resolver($item, $key);
            $value = is_bool($value) ? (int) $value : (is_scalar($value) || $value === null ? (string) $value : serialize($value));
            $counts[$value] = ($counts[$value] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * Join the items into a string, or one key of each item.
     */
    public function implode(string $glue, ?string $key = null): string
    {
        $values = $key === null ? $this->data : $this->pluck($key)->all();

        return implode($glue, array_map(fn($value) => (string) $value, $values));
    }

    /**
     * Map each item to an array or collection, then join the results into one level.
     *
     * @template TFlatMapValue
     * @param callable(TValue, TKey): (array<mixed, TFlatMapValue>|Collection<array-key, TFlatMapValue>) $callback
     * @return static<array-key, TFlatMapValue>
     */
    public function flatMap(callable $callback): static
    {
        return $this->map($callback)->collapse();
    }

    /**
     * Join an array of arrays (or collections) into a single level.
     *
     * @return static<array-key, mixed>
     */
    public function collapse(): static
    {
        return $this->flatten(1);
    }

    /**
     * Skip the first items.
     *
     * @return static<TKey, TValue>
     */
    public function skip(int $count): static
    {
        return new static((string) $this->model, array_slice($this->data, max(0, $count), null, true));
    }

    /**
     * Get a part of the collection.
     *
     * @return static<TKey, TValue>
     */
    public function slice(int $offset, ?int $length = null): static
    {
        return new static((string) $this->model, array_slice($this->data, $offset, $length, true));
    }

    /**
     * Add items at the end. Numeric keys are renumbered.
     *
     * @param iterable<mixed> $items
     * @return static<int, mixed>
     */
    public function concat(iterable $items): static
    {
        return $this->merge(is_array($items) ? array_values($items) : array_values(iterator_to_array($items)));
    }

    /**
     * Find the key of a value, or of the first item that passes a test.
     */
    public function search(mixed $value, bool $strict = false): int|string|false
    {
        $isTest = !is_string($value) && is_callable($value);

        foreach ($this->data as $key => $item) {
            if ($isTest ? $value($item, $key) : ($strict ? $item === $value : $item == $value)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * The middle value. Items that are null are ignored.
     */
    public function median(?string $key = null): int|float|null
    {
        $values = $key === null ? $this->data : $this->pluck($key)->all();
        $values = array_values(array_filter($values, fn($value) => $value !== null));

        if ($values === []) {
            return null;
        }

        sort($values);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1
            ? $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * Run a callback when the value is truthy.
     *
     * @template TWhen
     * @template TWhenDefault
     * @param callable($this, mixed): TWhen $callback
     * @param (callable($this, mixed): TWhenDefault)|null $default
     * @return TWhen|TWhenDefault|$this
     */
    public function when(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        $value = $value instanceof \Closure ? $value($this) : $value;

        if ($value) {
            return $callback($this, $value) ?? $this;
        }

        return $default ? ($default($this, $value) ?? $this) : $this;
    }

    /**
     * Run a callback when the value is falsy.
     *
     * @template TUnless
     * @template TUnlessDefault
     * @param callable($this, mixed): TUnless $callback
     * @param (callable($this, mixed): TUnlessDefault)|null $default
     * @return TUnless|TUnlessDefault|$this
     */
    public function unless(mixed $value, callable $callback, ?callable $default = null): mixed
    {
        $value = $value instanceof \Closure ? $value($this) : $value;

        if (!$value) {
            return $callback($this, $value) ?? $this;
        }

        return $default ? ($default($this, $value) ?? $this) : $this;
    }

    /**
     * Get all items in the collection.
     *
     * @return array<TKey, TValue>
     */
    public function get(): array
    {
        return $this->data;
    }
}
