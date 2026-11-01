<?php

declare(strict_types=1);

namespace UserBase\Security\Authentication;

use UserBase\Http\AuthenticationContext;
use UserBase\Http\RequestContext;
use UserBase\Service\SessionService;
use UserBase\Constants\Names;

final class SessionAuthenticator implements IAuthenticator
{

    public function __construct(
        private readonly SessionService $sessions
    ) {}

    public function Authenticate(
        RequestContext $context
    ): AuthenticationContext {
        $token = $context->GetCookie(Names::SESSION_COOKIE);

        if ($token === null)
        { return AuthenticationContext::Anonymous(); }

        $session = $this->sessions->Validate($token);

        if ($session === null)
        { return AuthenticationContext::Anonymous(); }

        $userID = $session['user_id'] ?? null;

        if (!is_string($userID) || $userID === '')
        { return AuthenticationContext::Anonymous(); }

        return AuthenticationContext::Authenticated($userID);
    }
}

?>