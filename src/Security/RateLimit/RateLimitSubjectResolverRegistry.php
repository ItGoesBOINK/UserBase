<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use InvalidArgumentException;

final class RateLimitSubjectResolverRegistry
{
    /** @var array<string, IRateLimitSubjectResolver> */
    private array $resolvers = [];

    public function Register(
        string $subjectType,
        IRateLimitSubjectResolver $resolver
    ): void {

        if (trim($subjectType) === '')
        { throw new InvalidArgumentException('Rate-Limit Subject Type cannot be empty'); }

        if (isset($this->resolvers[$subjectType]))
        { throw new InvalidArgumentException("Rate-Limit Subject Type [{$subjectType}] is already registered"); }

        $this->resolvers[$subjectType] = $resolver;
    }

    public function Find(string $subjectType): ?IRateLimitSubjectResolver
    { return $this->resolvers[$subjectType] ?? null; }
}

?>