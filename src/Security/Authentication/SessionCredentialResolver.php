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
            throw new CredentialConflictException(
                'Multiple session credentials were supplied'
            );
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
        if ($auth === null) return null;

        $auth = trim($auth);
        //$patt_bearerMatch = '/\ABearer[ \t]+(\S+)\z/i';  //'/Bearer\s+(\S+)$/i';

        if (!preg_match('/\ABearer\b/i', $auth)) {
            return null;
        }

        //echo ">>> Token: {$auth}\n";
        if (!preg_match('/\ABearer[ \t]+(\S+)\z/i', $auth, $matches)) {
            throw new InvalidArgumentException('Malformed Bearer credential');
        }

        return $matches[1];
    }
}


?>