<?php

declare(strict_types=1);

namespace UserBase\Controller;

use UserBase\Service\SessionService;
use UserBase\Http\RequestContext;
use UserBase\Constants\Names;

final class LogOutController extends ControllerBase
{
    public function __construct(
        private readonly SessionService $sessions
    ) {}

    #[\Override]
    public function Execute(RequestContext $context): void
    {
        // Revoke the server-side session, if a token was supplied
        $token = $context->GetCookie(Names::SESSION_COOKIE) ?? '';

        if ($token !== null) {
            $this->sessions->Revoke($token);
        }

        // Expire the browser cookie using the login cookie settings
        $isSecure = (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        );

        setcookie(
            Names::SESSION_COOKIE,
            '',
            [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        $this->Success(['message' => 'Log Out Successful']);
    }
}

?>