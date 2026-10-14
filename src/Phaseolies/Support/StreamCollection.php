<?php

namespace Phaseolies\Support;

use Traversable;
use IteratorAggregate;
use Countable;
use ArrayIterator;

/**
 * A collection that pulls its items one at a time, so large or endless sources can
 * be processed without loading them into memory.
 *
 * @template TKey of array-key
 * @template TValue
 * @implements IteratorAggregate<TKey, TValue>
 * @phpstan-consistent-constructor
 */
class StreamCollection implements IteratorAggregate, Countable
{
    /**
     * The source generator or iterator.
     *
     * @var (callable(): \Traversable<TKey, TValue>)|\Traversable<TKey, TValue>
     */
    protected $source;

    /**
     * Create a new lazy collection.
     *
     * @param (callable(): \Traversable<TKey, TValue>)|\Traversable<TKey, TValue>|array<TKey, TValue> $source
     */
    public function __construct(mixed $source)
    {
        if (is_array($source)) {
            /** @var \Traversable<TKey, TValue> $iterator */
            $iterator = new ArrayIterator($source);
            $this->source = $iterator;
            return;
        }

        /** @var (callable(): \Traversable<TKey, TValue>)|\Traversable<TKey, TValue> $source */
        $this->source = $source;
    }

    /**
     * Create a new lazy collection from a generator function, a Generator or Iterator, or an array.
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     * @param (callable(): \Traversable<TMakeKey, TMakeValue>)|\Traversable<TMakeKey, TMakeValue>|array<TMakeKey, TMakeValue> $source
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make(callable|Traversable|array $source): static
    {
        return new static($source);
    }

    /**
     * Create a stream of the numbers from $from to $to, one at a time.
     *
     * @param int|float $from
     * @param int|float $to
     * @param int|float $step How much to move each time; the direction follows from and to
     * @return static<int, int|float>
     */
    public static function range(int|float $from, int|float $to, int|float $step = 1): static
    {
        if ($step <= 0) {
            throw new \InvalidArgumentException('The step of a range must be greater than 0.');
        }

        return new static(function () use ($from, $to, $step) {
            if ($from <= $to) {
                for ($n = $from; $n <= $to; $n += $step) {
                    yield $n;
                }

                return;
            }

            for ($n = $from; $n >= $to; $n -= $step) {
                yield $n;
            }
        });
    }

    /**
     * Create a stream that calls a callback a number of times, passing 1, 2, 3...
     * Without a callback the numbers themselves are the items.
     *
     * @template TTimesValue
     * @param int $count
     * @param (callable(int): TTimesValue)|null $callback
     * @return static<int, mixed>
     */
    public static function times(int $count, ?callable $callback = null): static
    {
        return new static(function () use ($count, $callback) {
            for ($i = 1; $i <= $count; $i++) {
                yield $callback ? $callback($i) : $i;
            }
        });
    }

    /**
     * Create a stream of the lines of a file, read one at a time, without the line breaks.
     *
     * @param string $path
     * @return static<int, string>
     * @throws \InvalidArgumentException
     */
    public static function lines(string $path): static
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("The file [{$path}] does not exist or is not readable.");
        }

        return new static(function () use ($path) {
            $handle = fopen($path, 'rb');

            if ($handle === false) {
                return;
            }

            try {
                while (($line = fgets($handle)) !== false) {
                    yield rtrim($line, "\r\n");
                }
            } finally {
                fclose($handle);
            }
        });
    }

    /**
     * Remember the items as they are read, so the stream can be iterated more than once.
     *
     * @return static<TKey, TValue>
     */
    public function remember(): static
    {
        $cache = [];
        $iterator = null;
        $done = false;
        $source = $this;

        return new static(function () use (&$cache, &$iterator, &$done, $source) {
            $position = 0;

            while (true) {
                if ($position < count($cache)) {
                    [$key, $value] = $cache[$position++];

                    yield $key => $value;

                    continue;
                }

                if ($done) {
                    return;
                }

                if ($iterator === null) {
                    $iterator = new \IteratorIterator($source->getIterator());
                    $iterator->rewind();
                } else {
                    $iterator->next();
                }

                if (!$iterator->valid()) {
                    $done = true;

                    return;
                }

                $cache[] = [$iterator->key(), $iterator->current()];
            }
        });
    }

    /**
     * Get the iterator for the collection.
     *
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        $source = $this->source;

        if (is_callable($source)) {
            $result = $source();
            if (!$result instanceof Traversable) {
                throw new \InvalidArgumentException('Callable source must return a Traversable object.');
            }
            return $result;
        }

        return $source;
    }

    /**
     * Map over each item.
     *
     * @template TMapValue
     * @param callable(TValue, TKey): TMapValue $callback
     * @return static<TKey, TMapValue>
     */
    public function map(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Filter the collection using the given callback. Without a callback, every
     * "falsy" item (null, false, 0, '', []) is removed.
     *
     * @param (callable(TValue, TKey): bool)|null $callback
     * @return static<TKey, TValue>
     */
    public function filter(?callable $callback = null): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback === null ? (bool) $value : $callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Keep the items that do not pass the test (the opposite of filter).
     *
     * @param callable(TValue, TKey): bool $callback
     * @return static<TKey, TValue>
     */
    public function reject(callable $callback): static
    {
        return $this->filter(fn($value, $key) => !$callback($value, $key));
    }

    /**
     * Chunk the collection into chunks of the given size.
     *
     * @param int $size
     * @return static<int, Collection<TKey, TValue>>
     */
    public function chunk(int $size): static
    {
        return new static(function () use ($size) {
            $chunk = [];
            $count = 0;

            foreach ($this as $key => $value) {
                if (array_key_exists($key, $chunk)) {
                    $chunk[] = $value;
                } else {
                    $chunk[$key] = $value;
                }

                $count++;

                if ($count >= $size) {
                    yield new Collection('', $chunk);
                    $chunk = [];
                    $count = 0;
                }
            }

            if (!empty($chunk)) {
                yield new Collection('', $chunk);
            }
        });
    }

    /**
     * Execute a callback over each item. Return false from the callback to stop.
     *
     * @param callable(TValue, TKey): mixed $callback
     * @return $this
     */
    public function each(callable $callback): static
    {
        foreach ($this as $key => $value) {
            if ($callback($value, $key) === false) {
                break;
            }
        }

        return $this;
    }

    /**
     * Convert the lazy collection to a regular collection.
     *
     * @param string $model
     * @return Collection<TKey, TValue>
     */
    public function collect(string $model = ''): Collection
    {
        $items = [];

        foreach ($this as $key => $value) {
            $items[$key] = $value;
        }

        return new Collection($model, $items);
    }

    /**
     * Get all items as array.
     *
     * @return array<TKey, TValue>
     */
    public function all(): array
    {
        return iterator_to_array($this, true);
    }

    /**
     * Get the first item, or the first item that passes a test. Stops reading as
     * soon as it has found it.
     *
     * @param (callable(TValue, TKey): bool)|null $callback
     * @param mixed $default
     * @return TValue|mixed
     */
    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        foreach ($this as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                return $item;
            }
        }

        return $default instanceof \Closure ? $default() : $default;
    }

    /**
     * Get the last item, or the last item that passes a test. Reads the whole stream.
     *
     * @param (callable(TValue, TKey): bool)|null $callback
     * @param mixed $default
     * @return TValue|mixed
     */
    public function last(?callable $callback = null, mixed $default = null): mixed
    {
        $found = false;
        $last = null;

        foreach ($this as $key => $item) {
            if ($callback === null || $callback($item, $key)) {
                $found = true;
                $last = $item;
            }
        }

        return $found ? $last : ($default instanceof \Closure ? $default() : $default);
    }

    /**
     * Count the number of items in the collection. Reads the whole stream.
     *
     * @return int
     */
    public function count(): int
    {
        return iterator_count($this);
    }

    /**
     * Determine if the collection is empty or not.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        $iterator = new \IteratorIterator($this->getIterator());
        $iterator->rewind();

        return !$iterator->valid();
    }

    /**
     * Determine if the collection is not empty.
     *
     * @return bool
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Pluck an array of values from a given key.
     *
     * @param string $value
     * @param string|null $key
     * @return static<array-key, mixed>
     */
    public function pluck(string $value, ?string $key = null): static
    {
        return new static(function () use ($value, $key) {
            foreach ($this as $originalKey => $item) {
                $itemValue = $this->valueFrom($item, $value);

                if ($key === null) {
                    yield $originalKey => $itemValue;
                } else {
                    $itemKey = $this->valueFrom($item, $key);
                    if ($itemKey !== null && (is_string($itemKey) || is_int($itemKey))) {
                        yield $itemKey => $itemValue;
                    }
                }
            }
        });
    }

    /**
     * Read a key from an item, with dot notation for nested values.
     *
     * @param mixed $item
     * @param string|int $key
     * @return mixed
     */
    private function valueFrom(mixed $item, string|int $key): mixed
    {
        static $reader = null;
        $reader ??= new Collection('', []);

        return $reader->valueFrom($item, $key);
    }

    /**
     * Flatten the collection.
     *
     * @param int $depth
     * @return static<int, mixed>
     */
    public function flatten(int $depth = PHP_INT_MAX): static
    {
        return new static(function () use ($depth) {
            $flattenItem = function ($item, int $currentDepth) use (&$flattenItem, $depth) {
                if ($currentDepth >= $depth) {
                    yield $item;
                    return;
                }

                if (is_array($item)) {
                    foreach ($item as $subItem) {
                        yield from $flattenItem($subItem, $currentDepth + 1);
                    }
                } elseif ($item instanceof Collection) {
                    foreach ($item->all() as $subItem) {
                        yield from $flattenItem($subItem, $currentDepth + 1);
                    }
                } elseif ($item instanceof StreamCollection) {
                    foreach ($item as $subItem) {
                        yield from $flattenItem($subItem, $currentDepth + 1);
                    }
                } else {
                    yield $item;
                }
            };

            $position = 0;

            foreach ($this as $item) {
                foreach ($flattenItem($item, 0) as $leaf) {
                    yield $position++ => $leaf;
                }
            }
        });
    }

    /**
     * Get unique items from the collection.
     *
     * @param string|null $key
     * @param bool $strict
     * @return static<TKey, TValue>
     */
    public function unique(?string $key = null, bool $strict = false): static
    {
        return new static(function () use ($key, $strict) {
            $seen = [];

            foreach ($this as $item) {
                $value = $key !== null ? $this->valueFrom($item, $key) : $item;

                $id = $strict ? serialize($value) : (is_scalar($value) ? (string) $value : serialize($value));

                if (isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;

                yield $item;
            }
        });
    }

    /**
     * Skip the first $count items.
     *
     * @param int $count
     * @return static<TKey, TValue>
     */
    public function skip(int $count): static
    {
        return new static(function () use ($count) {
            $i = 0;
            foreach ($this as $key => $value) {
                if ($i++ >= $count) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Take the first $count items, reading no more than that from the source.
     *
     * @param int $count
     * @return static<TKey, TValue>
     */
    public function take(int $count): static
    {
        return new static(function () use ($count) {
            if ($count <= 0) {
                return;
            }

            $taken = 0;

            foreach ($this as $key => $value) {
                yield $key => $value;

                if (++$taken >= $count) {
                    break;
                }
            }
        });
    }

    /**
     * Take items while the test passes, and stop at the first that fails.
     *
     * @param callable(TValue, TKey): bool $callback
     * @return static<TKey, TValue>
     */
    public function takeWhile(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                if (!$callback($value, $key)) {
                    break;
                }

                yield $key => $value;
            }
        });
    }

    /**
     * Skip items while the test passes, then take the rest.
     *
     * @param callable(TValue, TKey): bool $callback
     * @return static<TKey, TValue>
     */
    public function skipWhile(callable $callback): static
    {
        return new static(function () use ($callback) {
            $skipping = true;

            foreach ($this as $key => $value) {
                if ($skipping && $callback($value, $key)) {
                    continue;
                }

                $skipping = false;

                yield $key => $value;
            }
        });
    }

    /**
     * Get the values (reset keys).
     *
     * @return static<int, TValue>
     */
    public function values(): static
    {
        return new static(function () {
            foreach ($this as $value) {
                yield $value;
            }
        });
    }

    /**
     * Add an item to the end of the collection.
     *
     * @param TValue $item
     * @return $this
     */
    public function push(mixed $item): static
    {
        $currentSource = $this->source;

        $this->source = function () use ($currentSource, $item) {
            foreach (is_callable($currentSource) ? $currentSource() : $currentSource as $key => $value) {
                yield $key => $value;
            }
            yield $item;
        };

        return $this;
    }

    /**
     * Add items at the end. Keys are not kept, the items are numbered.
     *
     * @param iterable<mixed> $items
     * @return static<int, mixed>
     */
    public function concat(iterable $items): static
    {
        return new static(function () use ($items) {
            foreach ($this as $value) {
                yield $value;
            }

            foreach ($items as $value) {
                yield $value;
            }
        });
    }

    /**
     * Map each item to an array, collection or stream, and join the results into one.
     *
     * @template TFlatMapValue
     * @param callable(TValue, TKey): (iterable<mixed, TFlatMapValue>|TFlatMapValue) $callback
     * @return static<array-key, TFlatMapValue>
     */
    public function flatMap(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                $result = $callback($value, $key);

                if (is_iterable($result)) {
                    foreach ($result as $inner) {
                        yield $inner;
                    }
                } else {
                    yield $result;
                }
            }
        });
    }

    /**
     * Reduce the stream to a single value.
     *
     * @template TReduce
     * @param callable(TReduce, TValue, TKey): TReduce $callback
     * @param TReduce $initial
     * @return TReduce
     */
    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        $carry = $initial;

        foreach ($this as $key => $value) {
            $carry = $callback($carry, $value, $key);
        }

        return $carry;
    }

    /**
     * Add up the items, or a key or callback result of each, reading one item at a time.
     *
     * @param (callable(TValue, TKey): (int|float))|string|null $callback
     * @return int|float
     */
    public function sum(callable|string|null $callback = null): int|float
    {
        return $this->reduce(fn($carry, $value, $key) => $carry + $this->measure($value, $key, $callback), 0);
    }

    /**
     * The average of the items, or null for an empty stream.
     *
     * @param (callable(TValue, TKey): (int|float))|string|null $callback
     * @return int|float|null
     */
    public function avg(callable|string|null $callback = null): int|float|null
    {
        $count = 0;
        $total = 0;

        foreach ($this as $key => $value) {
            $count++;
            $total += $this->measure($value, $key, $callback);
        }

        return $count === 0 ? null : $total / $count;
    }

    /**
     * The smallest value, or null for an empty stream.
     *
     * @param (callable(TValue, TKey): mixed)|string|null $callback
     * @return mixed
     */
    public function min(callable|string|null $callback = null): mixed
    {
        $found = false;
        $min = null;

        foreach ($this as $key => $value) {
            $measured = $this->measure($value, $key, $callback);

            if (!$found || $measured < $min) {
                $min = $measured;
                $found = true;
            }
        }

        return $min;
    }

    /**
     * The largest value, or null for an empty stream.
     *
     * @param (callable(TValue, TKey): mixed)|string|null $callback
     * @return mixed
     */
    public function max(callable|string|null $callback = null): mixed
    {
        $found = false;
        $max = null;

        foreach ($this as $key => $value) {
            $measured = $this->measure($value, $key, $callback);

            if (!$found || $measured > $max) {
                $max = $measured;
                $found = true;
            }
        }

        return $max;
    }

    /**
     * Extract the value to measure from an item using a key or callback.
     *
     * @param mixed $value
     * @param mixed $key
     * @param callable|string|null $callback
     * @return mixed
     */
    private function measure(mixed $value, mixed $key, callable|string|null $callback): mixed
    {
        if ($callback === null) {
            return $value;
        }

        return is_string($callback) ? $this->valueFrom($value, $callback) : $callback($value, $key);
    }

    /**
     * Determine whether every item passes the test. Stops at the first that fails.
     *
     * @param callable(TValue, TKey): bool $callback
     * @return bool
     */
    public function every(callable $callback): bool
    {
        foreach ($this as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine whether at least one item passes the test. Stops at the first that does.
     *
     * @param callable(TValue, TKey): bool $callback
     * @return bool
     */
    public function some(callable $callback): bool
    {
        foreach ($this as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pass the stream to a callback without changing it.
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
     * Pass the stream to a callback and return what it returns.
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
     * Convert the collection to JSON.
     *
     * @param int $options
     * @return string
     */
    public function toJson(int $options = 0): string
    {
        return $this->collect()->toJson($options);
    }

    /**
     * Convert the collection to an array.
     *
     * @return array<TKey, mixed>
     */
    public function toArray(): array
    {
        return $this->collect()->toArray();
    }
}