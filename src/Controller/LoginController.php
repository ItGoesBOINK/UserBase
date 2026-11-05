<?php

declare(strict_types=1);

namespace UserBase\Controller;

use JsonException;
use UserBase\Service\LoginService;
use UserBase\Service\SessionService;
use UserBase\Http\RequestContext;
use UserBase\Security\Cookies\SessionCookie;


final class LoginController extends ControllerBase
{
    public function __construct(
        private readonly LoginService $login,
        private readonly SessionCookie $sessionCookie,
        private readonly SessionService $sessionService
    ) {}

    #[\Override]
    public function Execute(RequestContext $context): void
    {
        try {

            $rawInput = file_get_contents('php://input');

            if ($rawInput === false) {
                $this->Error(
                    'invalid_request',
                    'Unable to read request body',
                    400
                );
            }

            $body = json_decode(
                $rawInput,
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

        $transport = $body['session_transport'] ?? 'cookie';

        if (
            !is_string($transport) ||
            !in_array($transport, ['cookie', 'bearer'], true)
        ) {
            $this->Error(
                'invalid_request',
                'Property "session_transport" must be either "cookie" or "bearer"',
                400
            );
        }

        $token = $this->login->Login(
            $body['username'],
            $body['password']
        );

        if ($transport === 'cookie') {
            if (!$this->sessionCookie->Set($token)) {
                $this->Error(
                    'session_cookie_error',
                    'Unable to establish the session cookie',
                    500
                );
            }

            $this->Success(['message' => 'Login Successful']);
        }

        $this->Success([
            'message' => 'Login Successful',
            'session_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->sessionService->GetLifetimeSeconds()
        ]);
    }
}

?>