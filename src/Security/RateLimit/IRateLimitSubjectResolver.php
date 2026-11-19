<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use UserBase\Http\RequestContext;

interface IRateLimitSubjectResolver
{
    public function Resolve(RequestContext $reqContext): ?string;
}

?>