<?php

declare(strict_types=1);

namespace UserBase\Controller;

use JsonException;
use UserBase\Service\LoginService;

final class LoginController extends ControllerBase
{
    private const string SESSION_COOKIE_NAME = 'userbase_session';
    private const int SESSION_LIFETIME_SECONDS = 86400;

    public function __construct(
        private readonly LoginService $login
    ) {}

    public function Login(): void
    {
        try {

            $body = json_decode(
                file_get_contents('php://input'),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            if (
                !is_array($body) ||
                !isset($body['username'], $body['password']) ||
                !is_string($body['username']) ||
                !is_string($body['password'])
            ) {
                $this->Error(
                    'invalid_request',
                    'User Name and Password are required to Login',
                    400
                );
            }

        } catch (JsonException $e) {
            $this->Error(
                'invalid_json',
                'The request body must contain valid JSON',
                400
            );
        }

        $token = $this->login->Login(
            $body['username'],
            $body['password']
        );

        $isSecure = (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== 'off'
        );

        setcookie(
            self::SESSION_COOKIE_NAME,
            $token,
            [
                'expires' => time() + self::SESSION_LIFETIME_SECONDS,
                'path' => '/',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );

        $this->Success(['message' => 'Login Successful']);
    }
}

?>