<?php

declare(strict_types=1);

namespace UserBase\Http;

class RouteModule
{
    /** @var array<RouteDescriptor> */
    public readonly ?array $descriptors;

    public function __construct(
        /** @var array<RouteDescriptor> */
        ?array $descriptors = null
    ) {
        $this->descriptors = $descriptors;
    }
}

?>