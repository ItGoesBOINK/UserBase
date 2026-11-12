<?php

declare(strict_types=1);

namespace UserBase\Security\Authentication;

final readonly class SessionGrant
{
    public function __construct(
        public string $sessionToken
    ) {}
}

?>