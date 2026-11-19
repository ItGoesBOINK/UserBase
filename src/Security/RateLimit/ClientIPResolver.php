<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use RuntimeException;
use UserBase\Http\RequestContext;

final class ClientIPResolver implements IRateLimitSubjectResolver
{
    public function Resolve(RequestContext $reqContext): ?string
    {
        $ip = $reqContext->clientIP;

        if (!filter_var($ip, FILTER_VALIDATE_IP))
        { throw new RuntimeException(
                'Unable to resolve a valid client IP address'); }

        return $ip;
    }
}

?>