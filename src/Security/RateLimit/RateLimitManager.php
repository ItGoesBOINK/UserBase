<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use UserBase\Config\Configuration;

final class RateLimitManager
{
    private readonly RateLimitPolicyRegistry $policies;
    private readonly RateLimitSubjectResolverRegistry $resolvers

    public function __construct(Configuration $config)
    {
        $this->policies = new RateLimitPolicyRegistry();
        $this->resolvers = new RateLimitSubjectResolverRegistry();
    }

    public function RegisterResolver(
        string $subjectType,
        IRateLimitSubjectResolver $resolver
    ): void {
        $this->resolvers->Register($subjectType, $resolver);
    }

    public function AddPolicy(
        string $policyKey,
        int $attemptMax,
        int $interval,
        string $subjectType
    ): void {
        $policy = new RateLimitPolicy($policyKey, $attemptMax, $interval);
        $this->policies->Register($policy, $subjectType);
    }

    public function FindPolicy(string $policyKey): ?RateLimitPolicyDefinition
    { return $this->resolvers->Find($subjectType); }


}

?>