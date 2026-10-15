<?php

namespace Phaseolies\Cache;

use Psr\SimpleCache\InvalidArgumentException;

class RateLimiter
{
    /**
     * The cache store implementation.
     */
    protected IncrementableCacheInterface $cache;

    /**
     * Create a new rate limiter instance.
     */
    public function __construct(IncrementableCacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @throws InvalidArgumentException
     */
    public function attempt(string $key, int $maxAttempts, int $decaySeconds): RateLimit
    {
        $timerKey = $key . '_timer';
        $now = time();
        $resetAt = $now + $decaySeconds;

        try {
            if ($this->cache->add($key, 1, $decaySeconds)) {
                $this->cache->add($timerKey, $resetAt, $decaySeconds);
                $hits = 1;
            } else {
                $hits = $this->cache->increment($key);

                if (!is_int($hits)) {
                    $this->cache->set($key, 1, $decaySeconds);
                    $this->cache->set($timerKey, $resetAt, $decaySeconds);
                    $hits = 1;
                }
            }

            $remaining = max(0, $maxAttempts - $hits);
            $resetAt = (int) ($this->cache->get($timerKey) ?? ($now + $decaySeconds));

            return new RateLimit(
                limit: $maxAttempts,
                remaining: $remaining,
                resetAt: $resetAt,
            );
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @throws InvalidArgumentException
     */
    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        try {
            return $this->attempts($key) >= $maxAttempts;
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * Get the number of seconds until the "key" is accessible again.
     *
     * @throws InvalidArgumentException
     */
    public function availableIn(string $key): int
    {
        try {
            $timer = $this->cache->get($key . '_timer');

            if ($timer === null) {
                return 0;
            }

            return max(0, (int) $timer - time());
        } catch (InvalidArgumentException $e) {
            return 0;
        }
    }

    /**
     * Get the expiration timestamp for the rate limit.
     */
    public function availableAt(int $seconds): int
    {
        return time() + $seconds;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @throws InvalidArgumentException
     */
    public function hit(string $key, int $decaySeconds): int
    {
        $timerKey = $key . '_timer';
        $now = time();
        $resetAt = $now + $decaySeconds;

        try {
            if ($this->cache->add($key, 1, $decaySeconds)) {
                $this->cache->add($timerKey, $resetAt, $decaySeconds);
                return 1;
            }

            $hits = $this->cache->increment($key);

            if (!is_int($hits)) {
                $this->cache->set($key, 1, $decaySeconds);
                $this->cache->set($timerKey, $resetAt, $decaySeconds);
                return 1;
            }

            return $hits;
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Clear the hits and lockout timer for the given key.
     *
     * @throws InvalidArgumentException
     */
    public function clear(string $key): void
    {
        try {
            $this->cache->delete($key);
            $this->cache->delete($key . '_timer');
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @throws InvalidArgumentException
     */
    public function attempts(string $key): int
    {
        try {
            return (int) ($this->cache->get($key) ?? 0);
        } catch (InvalidArgumentException $e) {
            return 0;
        }
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @throws InvalidArgumentException
     */
    public function resetAttempts(string $key): void
    {
        try {
            $this->cache->delete($key);
        } catch (InvalidArgumentException $e) {
            throw $e;
        }
    }
}