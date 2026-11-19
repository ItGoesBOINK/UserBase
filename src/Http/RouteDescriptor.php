<?php

declare(strict_types=1);

namespace UserBase\Http;

use InvalidArgumentException;
use UserBase\Security\RateLimit\RateLimitRequirement;

final class RouteDescriptor
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly AuthenticationRequirement $authReq,
        public readonly IRequestHandler $handler,
        public readonly bool $requiresCsrf = false,
        public readonly array $rateLimits = []
    ) {

        $allowed = ['GET', 'POST', 'OPTIONS'];
        $m = strtoupper($method);
        if (!in_array($m, $allowed, true)) {
            throw new InvalidArgumentException(
                "Method [{$method}] is not allowed"
            );
        }

        foreach ($this->rateLimits as $policyKey)
        {
            if (!is_string($policyKey) || $policyKey === '')  {
                throw new InvalidArgumentException('Rate-Limits must contain RateLimitRequirement instances');
            }
        }

    }
}


?>