<?php

declare(strict_types=1);

namespace UserBase\Http;

use InvalidArgumentException;

final class RouteDescriptor
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly AuthenticationRequirement $authReq,
        public readonly IRequestHandler $handler,
        public readonly bool $requiresCsrf = false
    ) {

        $allowed = ['GET', 'POST', 'OPTIONS'];
        $m = strtoupper($method);
        if (!in_array($m, $allowed)) {
            throw new InvalidArgumentException(
                "Method [{$method}] is not allowed"
            );
        }

    }
}


?>