<?php

declare(strict_types=1);

namespace UserBase\Security\Authentication;

use UserBase\Http\AuthenticationContext;
use UserBase\Http\RequestContext;
use UserBase\Service\SessionService;
use UserBase\Config\SessionSettings;

final class SessionAuthenticator implements IAuthenticator
{

    public function __construct(
        private readonly SessionService $sessions,
        private readonly SessionCredentialResolver $credentialResolver
    ) {}

    public function Authenticate(
        RequestContext $context
    ): AuthenticationContext {

        $credential = $this->credentialResolver->Resolve($context);

        //error_log('Cookie received: ' . ($context->GetCookie('userbase_session') !== null ? 'YES' : 'NO'));
        //error_log('Credential resolved: ' . ($credential !== null ? 'YES' : 'NO'));
        //error_log('Credential type: ' . ($credential?->type->name ?? 'NONE'));

        if ($credential === null)
            { return AuthenticationContext::Anonymous(); }

        $session = $this->sessions->Validate($credential->token);

        //error_log('Session validated: ' . ($session !== null ? 'YES' : 'NO'));

        if ($session === null)
            { return AuthenticationContext::Anonymous(); }

        $userID = $session['user_id'] ?? null;

        //error_log('Session user_id: '. var_export($userID, true));
        //error_log('Session user_id type: '. gettype($userID));

        $isInt = is_int($userID);
        $isStr = is_string($userID);
        $isDigit = ctype_digit((string)$userID);
        $zeroOrLess = (int)$userID <= 0;

        $isInvalid = (!$isInt && !$isStr) ||
                   !$isDigit || $zeroOrLess;

        if ($isInvalid)
            { return AuthenticationContext::Anonymous(); }

        //error_log('Authentication context: AUTHENTICATED');

        return AuthenticationContext::Authenticated((string) $userID, $credential->type);
    }
}

?>