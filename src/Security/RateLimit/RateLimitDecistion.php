<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use DateTimeImmutable;

final readonly class RateLimitDecision
{
    public function __construct(
        public bool $allowed,
        public int $remainingAttempts,
        public ?DateTimeImmutable $retryAt = null
    ) {}
}

?>