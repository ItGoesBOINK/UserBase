<?php

declare(strict_types=1);

namespace UserBase\Http;

use UserBase\Security\Authentication\SessionCredential;

final readonly class AuthenticationResult
{
    public function __construct(
        public AuthenticationContext $authContext,
        public ?SessionCredential $credential
    ) {}
}

?>