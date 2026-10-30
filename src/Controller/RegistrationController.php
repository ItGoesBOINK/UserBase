<?php

declare(strict_types=1);

namespace UserBase\Controller;
use PDO;
use UserBase\Service\RegistrationService;
use UserBase\Http\RequestContext;

final class RegistrationController extends ControllerBase
{

    public function __construct(
        private readonly RegistrationService $registration
    ){}


    #[\Override]
    public function Execute(RequestContext $context): void
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
                !isset($body['username'], $body['email'], $body['password']) ||
                !is_string($body['username']) ||
                !is_string($body['email']) ||
                !is_string($body['password'])
            ) {
                $this->Error(
                    'invalid_request',
                    'User Name, E-Mail, and Password are required to register a new account',
                    400
                );
            }

            $userID = $this->registration->Register(
                $body['username'],
                $body['email'],
                $body['password']
            );

        } catch (\JsonException $e) {
            $this->Error(
                'invalid_json',
                'The request body must contain valid JSON',
                400
            );
        }



        $this->Success([
            'user_id' => $userID,
            'message' => 'Registration successful.  Please check your E-Mail to verify your account, and log-in'
        ], 201);
    }
}