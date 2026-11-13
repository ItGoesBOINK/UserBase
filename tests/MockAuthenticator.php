<?php

declare(strict_types=1);

namespace UserBase\Tests;

use UserBase\Http\AuthenticationContext;
use UserBase\Http\AuthenticationResult;
use UserBase\Http\RequestContext;
use UserBase\Security\Authentication\IAuthenticator;
use UserBase\Security\Authentication\SessionCredential;

final class MockAuthenticator implements IAuthenticator
{
    public function __construct(
        private readonly AuthenticationContext $authContext,
        private readonly ?SessionCredential $credential = null
    ) {}

    public function Authenticate(
        RequestContext $reqContext
    ): AuthenticationResult {
        return new AuthenticationResult($this->authContext, $this->credential);
    }
}


?>