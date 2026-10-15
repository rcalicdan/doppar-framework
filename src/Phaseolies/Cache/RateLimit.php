<?php

namespace Phaseolies\Cache;

class RateLimit
{
    /**
     * Create a new rate limit instance.
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $resetAt,
    ) {}
}
