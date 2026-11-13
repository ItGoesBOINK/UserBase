<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

final readonly class RateLimitPolicy
{
    public function __construct(
        public string $key,
        public int $maxAttempts,
        public int $windowSeconds
    ) {}
}

?>