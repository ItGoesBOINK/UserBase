<?php

declare(strict_types=1);

namespace UserBase\Config;

final readonly class SessionSettings
{
    public function __construct(
        public string $cookieName,
        public string $cookiePath,
        public string $sameSitePolicy,
        public bool $httpOnly,
        public int $lifetimeSeconds
    ) {}
}

?>