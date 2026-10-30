<?php

declare(strict_types=1);

namespace UserBase\Http;

use UserBase\Controller\ControllerBase;

final readonly class Route
{
    public function __construct(
        public ControllerBase $controller,
        public RoutePolicy $policy
    ) {}
}

?>