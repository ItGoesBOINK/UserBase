<?php

declare(strict_types=1);

namespace UserBase\Controller;

use InvalidArgumentException;
use UserBase\Http\RequestContext;
use UserBase\Service\SessionService;
use UserBase\Security\Cookies\SessionCookie;
use UserBase\Security\Authentication\SessionCredentialType;
use UserBase\Security\Authentication\SessionCredentialResolver;

final class LogOutController extends ControllerBase
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly SessionCredentialResolver $credentialResolver,
        private readonly SessionCookie $cookie
    ) {}

    #[\Override]
    public function Execute(RequestContext $context): void
    {
        $credential = null;
        try {
            $credential = $this->credentialResolver->Resolve($context);
        } catch (InvalidArgumentException $e) {
            $this->Error(
                'invalid_request',
                'Multiple, or invalid session credentials were supplied',
                400
            );
        }

        if ($credential !== null) {
            $this->sessions->Revoke($credential->token);

            if ($credential->type === SessionCredentialType::Cookie) {
                if (!$this->cookie->Clear()) {
                    $this->Error(
                        'session_cookie_error',
                        'Unable to clear the session cookie',
                        500
                    );
                }
            }
        }

        $this->Success(['message' => 'Log Out Successful']);
    }
}

?>