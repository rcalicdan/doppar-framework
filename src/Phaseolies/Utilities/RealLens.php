<?php

namespace Phaseolies\Utilities;

use Phaseolies\Support\Contracts\Lensable;

class RealLens implements Lensable
{
    /**
     * Retrieve an item from an array using "dot" notation.
     *
     * @param array<string|int, mixed> $array
     */
    public function grab(array $array, string|int|null $key, mixed $default = null): mixed
    {
        if ($key === null) return $array;

        $keys = explode('.', (string) $key);

        foreach ($keys as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Set an array item to a given value using "dot" notation.
     *
     * @param array<string|int, mixed> $array
     */
    public function put(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &$array;

        foreach ($keys as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }

        $ref = $value;
    }

    /**
     * Check if one or more items exist in an array using "dot" notation.
     *
     * @param array<string|int, mixed> $array
     * @param string|array<int, string> $keys
     */
    public function got(array $array, string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            $ref = $array;
            foreach (explode('.', $key) as $segment) {
                if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                    return false;
                }
                $ref = $ref[$segment];
            }
        }

        return true;
    }

    /**
     * Check if at least one of the given items exists in an array using "dot" notation.
     *
     * @param array<string|int, mixed> $array
     * @param string|array<int, string> $keys
     */
    public function some(array $array, string|array $keys): bool
    {
        foreach ((array) $keys as $key) {
            if ($this->got($array, $key)) return true;
        }

        return false;
    }

    /**
     * Remove one or more array items from a given array using "dot" notation.
     *
     * @param array<string|int, mixed> $array
     * @param string|array<int, string> $keys
     */
    public function zap(array &$array, string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            $segments = explode('.', $key);
            $ref = &$array;

            while (count($segments) > 1) {
                $segment = array_shift($segments);
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    continue 2;
                }
                $ref = &$ref[$segment];
            }

            unset($ref[array_shift($segments)]);
        }
    }

    /**
     * Pluck an array of values from an array of arrays or objects.
     *
     * @param array<string|int, mixed> $array
     * @return array<string|int, mixed>
     */
    public function pick(array $array, string $value, ?string $key = null): array
    {
        $results = [];

        foreach ($array as $item) {
            $val = is_array($item) ? $this->grab($item, $value) : ($item->{$value} ?? null);
            if ($key !== null) {
                $k = is_array($item) ? $this->grab($item, $key) : ($item->{$key} ?? null);
                if ($k !== null && (is_string($k) || is_int($k))) {
                    $results[$k] = $val;
                }
            } else {
                $results[] = $val;
            }
        }

        return $results;
    }

    /**
     * Flatten a multi-dimensional array into a single level.
     *
     * @param array<string|int, mixed> $array
     * @return array<int, mixed>
     */
    public function flat(array $array, int $depth = \PHP_INT_MAX): array
    {
        $result = [];
        $stack = [['array' => $array, 'depth' => $depth]];

        while (!empty($stack)) {
            $current = array_pop($stack);
            foreach ($current['array'] as $item) {
                if (!is_array($item) || $current['depth'] === 0) {
                    $result[] = $item;
                } elseif ($current['depth'] === 1) {
                    $result = array_merge($result, $item);
                } else {
                    $stack[] = ['array' => $item, 'depth' => $current['depth'] - 1];
                }
            }
        }

        return $result;
    }

    /**
     * Get the first element of an array, optionally filtered by a callback.
     *
     * @param array<string|int, mixed> $array
     */
    public function head(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return reset($array) ?: $default;
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) return $value;
        }

        return $default;
    }

    /**
     * Get the last element of an array, optionally filtered by a callback.
     *
     * @param array<string|int, mixed> $array
     */
    public function tail(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        foreach (array_reverse($array, true) as $key => $value) {
            if ($callback($value, $key)) return $value;
        }

        return $default;
    }

    /**
     * Flatten a multi-dimensional array into a single level (shallow flatten).
     *
     * @param array<string|int, mixed> $array
     * @return array<int, mixed>
     */
    public function squash(array $array): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item)) {
                foreach ($item as $v) {
                    $result[] = $v;
                }
            }
        }

        return $result;
    }

    /**
     * Return only the specified keys from the array.
     *
     * @param array<string|int, mixed> $array
     * @param array<int, string|int> $keys
     * @return array<string|int, mixed>
     */
    public function keep(array $array, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $result[$key] = $array[$key];
            }
        }

        return $result;
    }

    /**
     * Remove the specified keys from the array.
     *
     * @param array<string|int, mixed> $array
     * @param array<int, string|int> $keys
     * @return array<string|int, mixed>
     */
    public function drop(array $array, array $keys): array
    {
        foreach ($keys as $key) {
            unset($array[$key]);
        }

        return $array;
    }

    /**
     * Determine if the array is associative (non-sequential).
     *
     * @param array<string|int, mixed> $array
     */
    public function assoc(array $array): bool
    {
        if ([] === $array) return false;

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * Filter the array using the given callback.
     *
     * @param array<string|int, mixed> $array
     * @return array<string|int, mixed>
     */
    public function whr(array $array, callable $callback): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * If the given value is not an array, wrap it in one.
     *
     * @return array<int, mixed>
     */
    public function wrap(mixed $value): array
    {
        if (is_null($value)) return [];

        return is_array($value) ? $value : [$value];
    }

    /**
     * Flatten a multi-dimensional associative array with dots.
     *
     * @param array<string|int, mixed> $array
     * @return array<string, mixed>
     */
    public function dot(array $array, string $prepend = ''): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            $fullKey = $prepend . $key;

            if (is_array($value) && $this->assoc($value)) {
                $results += $this->dot($value, $fullKey . '.');
            } else {
                $results[$fullKey] = $value;
            }
        }

        return $results;
    }

    /**
     * Convert a flattened "dot" notation array into an expanded array.
     *
     * @param array<string, mixed> $array
     * @return array<string|int, mixed>
     */
    public function undot(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $this->put($result, $key, $value);
        }

        return $result;
    }

    /**
     * Randomly shuffle the values of the given array.
     *
     * @param array<string|int, mixed> $array
     * @return array<int, mixed>
     */
    public function rand(array $array): array
    {
        $array = array_values($array);

        for ($i = count($array) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$array[$i], $array[$j]] = [$array[$j], $array[$i]];
        }

        return $array;
    }
}