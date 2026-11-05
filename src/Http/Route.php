<?php

declare(strict_types=1);

namespace UserBase\Http;

final readonly class Route
{
    public function __construct(
        public IRequestHandler $handler,
        public RoutePolicy $policy
    ) {}
}

?>