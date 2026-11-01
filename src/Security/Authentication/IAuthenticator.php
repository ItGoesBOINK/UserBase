<?php

declare(strict_types=1);

namespace UserBase\Security\Authentication;

use UserBase\Http\AuthenticationContext;
use UserBase\Http\RequestContext;

interface IAuthenticator
{
    public function Authenticate(
        RequestContext $context
    ): AuthenticationContext;
}

?>