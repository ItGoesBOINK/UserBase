<?php

declare(strict_types=1);

namespace UserBase\Tests;

use UserBase\Http\AuthenticationContext;
use UserBase\Http\RequestContext;
use UserBase\Security\Authentication\IAuthenticator;

final class MockAuthenticator implements IAuthenticator
{
    public function __construct(
        private readonly AuthenticationContext $auth
    ) {}

    public function Authenticate(
        RequestContext $context
    ): AuthenticationContext {
        return $this->auth;
    }
}


?>