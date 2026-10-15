<?php

namespace Phaseolies\Cache;

use Psr\SimpleCache\CacheInterface;

interface IncrementableCacheInterface extends CacheInterface
{
    /**
     * Increment the value of an item in the cache.
     */
    public function increment(string $key, int $value = 1): int|bool;

    /**
     * Store an item in the cache if the key doesn't exist.
     */
    public function add(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool;
}