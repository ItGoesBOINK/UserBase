<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

final readonly class RateLimitPolicyDefinition
{
    public function __construct(
        public RateLimitPolicy $policy,
        public string $subjectType
    ) {}
}

?>