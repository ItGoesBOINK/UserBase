<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use InvalidArgumentException;
use UserBase\Config\Configuration;

final class RateLimitManager
{
    public readonly RateLimitPolicyRegistry $policies;
    public readonly RateLimitSubjectResolverRegistry $resolvers;

    public function __construct(private Configuration $config)
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

        if ($this->FindResolver($subjectType) === null) {
            throw new InvalidArgumentException(
                "No Resolver is registered for Rate-Limit Subject-Type [{$subjectType}]"
            );
        }

        if ($interval < $this->config->rateLimitMaxRetentionHours) {
            throw new InvalidArgumentException(
                "Invalid Rate-Limit retention window: [{$interval}]"
            );
        }

        $policy = new RateLimitPolicy($policyKey, $attemptMax, $interval);
        $this->policies->Register($policy, $subjectType);
    }

    public function FindPolicy(string $policyKey): ?RateLimitPolicyDefinition
    { return $this->policies->Find($policyKey); }

    public function FindResolver(string $subjectType): ?IRateLimitSubjectResolver
    { return $this->resolvers->Find($subjectType); }


}

?>