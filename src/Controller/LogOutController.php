<?php

declare(strict_types=1);

namespace UserBase\Controller;

use UserBase\Service\SessionService;

final class LogOutController extends ControllerBase
{
    private const string SESSION_COOKIE_NAME = 'userbase_session';

    public function __construct(
        private readonly SessionService $sessions
    ) {}

    #[\Override]
    public function Execute(): void
    {
        // Revoke the server-side session, if a token was supplied
        $token = $_COOKIE[self::SESSION_COOKIE_NAME] ?? '';

        if (is_string($token)) {
            $this->sessions->Revoke($token);
        }

        // Expire the browser cookie using the login cookie settings
        $isSecure = (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        );

        setcookie(
            self::SESSION_COOKIE_NAME,
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