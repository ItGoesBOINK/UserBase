<?php

declare(strict_types=1);

namespace UserBase\Controller;

use UserBase\Http\RequestContext;

final class CSRFTokenController extends ControllerBase
{
    public function __construct(
        private readonly CSRFTokenService $tokenService
    ) {}

    #[Override]
    public function Execute(RequestContext $reqContext): void
    {
        // Verify cookie authentication

        // Obtain the authenticated session token

        // Derive the CSRF Token

        // Return the token as JSON
    }
}

?>