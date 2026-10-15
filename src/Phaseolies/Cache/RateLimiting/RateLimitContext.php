<?php

namespace Phaseolies\Cache\RateLimiting;

class RateLimitContext
{
    /**
     * Create a new rate limit context instance.
     */
    public function __construct(
        public readonly string $key,
        public readonly int $maxAttempts,
        public readonly int $decaySeconds,
    ) {}
}
