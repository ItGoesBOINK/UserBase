<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

interface IRateLimitSubjectResolver
{
    public function Resolve(object $request): string;
}

?>