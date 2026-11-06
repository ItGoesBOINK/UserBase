<?php

declare(strict_types=1);

namespace UserBase\Security\Authentication;

use InvalidArgumentException;
use UserBase\Exception\CredentialConflictException;
use UserBase\Http\ExceptionHandler;
use UserBase\Http\RequestContext;
use UserBase\Security\Cookies\SessionCookie;

final class SessionCredentialResolver
{
    public function __construct(
        public SessionCookie $cookie
    ) {}

    public function Resolve(RequestContext $reqContext): ?SessionCredential
    {
        $cookieToken = $this->cookie->GetToken($reqContext);
        $bearerToken = $this->GetBearerToken($reqContext);

        if ($cookieToken !== null && $bearerToken !== null) {
            ExceptionHandler::Handle( new CredentialConflictException(
                'Multiple session credentials were supplied'
            ));
        }

        if ($bearerToken !== null) {
            return new SessionCredential
            ($bearerToken, SessionCredentialType::Bearer);
        }

        if ($cookieToken !== null) {
            return new SessionCredential
            ($cookieToken, SessionCredentialType::Cookie);
        }

        return null;
    }

    private function GetBearerToken(RequestContext $reqContext): ?string
    {
        $auth = $reqContext->GetHeader('authorization');
        $patt = '/^Bearer\s+(\S+)$/i';

        if ($auth === null) return null;
        if (!preg_match($patt, trim($auth), $matches)) return null;

        return $matches[1];
    }
}


?>