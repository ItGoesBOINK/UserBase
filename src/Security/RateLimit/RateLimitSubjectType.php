<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

enum RateLimitSubjectType
{
    case ClientIP;
    case AccountID;
}

?>