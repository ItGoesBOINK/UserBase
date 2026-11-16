<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use InvalidArgumentException;

final class RateLimitPolicyRegistry
{
    /** @var array<string, RateLimitPolicyDefinition> */
    private array $definitions = [];

    public function Register(
        RateLimitPolicy $policy,
        string $subjectType
    ): void {

        $k = $policy->key;

        if ($k === '') {
        throw new InvalidArgumentException
        ('Rate-Limit Policy Key cannot be empty'); }

        if (isset($this->definitions[$k])) {
        throw new InvalidArgumentException
        ("Rate-Limit Policy [{$k}] is already registered"); }

        if ($subjectType === '') {
        throw new InvalidArgumentException
        ("Rate-Limit Policy subject-type cannot be empty"); }


        $this->definitions[$k] =
        new RateLimitPolicyDefinition($policy, $subjectType);
    }

    public function Find(string $policyKey): ?RateLimitPolicyDefinition
    { return $this->definitions[$policyKey] ?? null; }
}

?>