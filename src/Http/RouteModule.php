<?php

declare(strict_types=1);

namespace UserBase\Http;

public class RouteModule
{
    /** @var array<array<string, string, AuthenticationRequirement, IRequestHandler>> */
    protected readonly array $descriptors;

    public function __construct(
        /** @var array<array<string, string, AuthenticationRequirement, IRequestHandler>> */
        array $routeDescriptors
    ) {
        $this->descriptors = $routeDescriptors;
    }

    public function GetCount(): int {
        return count($this->descriptors);
    }

    public function GetRoute():
}

?>