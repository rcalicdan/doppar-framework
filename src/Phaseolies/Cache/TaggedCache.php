<?php

namespace Phaseolies\Cache;

use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Contracts\Cache\ItemInterface;

class TaggedCache
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(
        protected CacheStore $store,
        protected TagAwareAdapter $pool,
        protected array $tags
    ) {
        if ($this->tags === []) {
            throw new \InvalidArgumentException('At least one cache tag is required.');
        }
    }

    /**
     * Get a tagged item.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $item = $this->pool->getItem($this->store->validatedKey($key));

        return $item->isHit() ? $item->get() : $default;
    }

    /**
     * Store an item with this cache's tags.
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        $item = $this->pool->getItem($this->store->validatedKey($key));
        $item->set($value)->tag($this->tags);

        $seconds = $this->store->ttlSeconds($ttl);

        if ($seconds !== null) {
            $item->expiresAfter($seconds);
        }

        return $this->pool->save($item);
    }

    /**
     * Store an item with this cache's tags until it is flushed.
     */
    public function forever(string $key, mixed $value): bool
    {
        $item = $this->pool->getItem($this->store->validatedKey($key));
        $item->set($value)->tag($this->tags)->expiresAfter(null);

        return $this->pool->save($item);
    }

    /**
     * Determine whether a tagged item exists.
     */
    public function has(string $key): bool
    {
        return $this->pool->hasItem($this->store->validatedKey($key));
    }

    /**
     * Remove one tagged item.
     */
    public function delete(string $key): bool
    {
        return $this->pool->deleteItem($this->store->validatedKey($key));
    }

    /**
     * Remove one tagged item.
     */
    public function forget(string $key): bool
    {
        return $this->delete($key);
    }

    /**
     * Get a tagged item and remove it in one step.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $key = $this->store->validatedKey($key);
        $item = $this->pool->getItem($key);

        if (!$item->isHit()) {
            return $default;
        }

        $value = $item->get();
        $this->pool->deleteItem($key);

        return $value;
    }

    /**
     * Get a tagged item, or run the callback and store its result with these tags.
     */
    public function stash(string $key, null|int|\DateInterval $ttl, \Closure $callback): mixed
    {
        return $this->remember($key, $this->store->ttlSeconds($ttl), $callback);
    }

    /**
     * Get a tagged item, or run the callback and store its result until it is flushed.
     */
    public function stashForever(string $key, \Closure $callback): mixed
    {
        return $this->remember($key, null, $callback);
    }

    /**
     * Remove every item that has any of this cache's tags.
     */
    public function flush(): bool
    {
        return $this->pool->invalidateTags($this->tags);
    }

    /**
     * The tags this cache writes with.
     *
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    protected function remember(string $key, ?int $seconds, \Closure $callback): mixed
    {
        return $this->pool->get(
            $this->store->validatedKey($key),
            function (ItemInterface $item) use ($seconds, $callback) {
                $item->tag($this->tags)->expiresAfter($seconds);

                return $callback();
            }
        );
    }
}