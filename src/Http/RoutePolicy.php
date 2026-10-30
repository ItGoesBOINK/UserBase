<?php

declare(strict_types=1);

namespace UserBase\Http;

final readonly class RoutePolicy
{
    public function __construct(
        public AuthenticationRequirement $authReq
    ) {}
}

?>