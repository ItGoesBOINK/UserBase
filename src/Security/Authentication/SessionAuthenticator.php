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

        // error_log('Session cookie received: ' . ($token !== null ? 'YES' : 'NO'));

        if ($token === null)
        { return AuthenticationContext::Anonymous(); }

        $session = $this->sessions->Validate($token);

        // error_log('Session Data: ' . print_r($session, true));

        // error_log('Session Validation: ' . ($session !== null ? 'VALID' : 'INVALID'));

        if ($session === null)
        { return AuthenticationContext::Anonymous(); }

        $userID = $session['user_id'] ?? null;
        //error_log('Authenticator User ID: ' . $userID);

        $isInt = is_int($userID);
        $isPositive = $userID >= 0;
        $isEmpty = $userID === '';
        $validID = $isInt && $isPositive && !$isEmpty;


        /*
        error_log('ID is Empty: ' . ($isEmpty ? '[EMPTY]' : $userID));
        error_log('ID is Int: ' . ($isInt ? $userID : '[NOT AN INT]'));
        error_log('ID is Positive: ' . ($isPositive ? 'POSITIVE' : 'NEGATIVE'));
        error_log('Valid ID: ' . ($validID ? 'YES' : 'NO'));
        */

        if (!$validID)
        { return AuthenticationContext::Anonymous(); }

        $auth = AuthenticationContext::Authenticated((string) $userID);
        $isAuth = $auth->IsAuthenticated();
        $label = $isAuth ? 'AUTHENTICATED' : 'ANONYMOUS';

        //error_log('Authenticator Identity: ' . $label);

        return $auth;
    }
}

?>