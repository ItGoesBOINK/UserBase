<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

final readonly class RateLimitRequirement
{
    public function __construct(
        public string $policyKey
    ) {}
}

?>