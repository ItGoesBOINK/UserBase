<?php

declare(strict_types=1);

namespace UserBase\Controller;
use UserBase\Http\RequestContext;
use UserBase\Service\EmailVerificationService;

final class EmailVerificationController extends ControllerBase
{
    private const string TOKEN_ID = 'token';

    public function __construct(
        private readonly EmailVerificationService $verification
    ){}


    #[\Override]
    public function Execute(RequestContext $context): void
    {
        try {
            $body = json_decode(
                file_get_contents('php://input'),
                true, 512, JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $e) {
            $this->Error(
            'invalid_json',
            'The request body must contain valid JSON',
            400
            );
        }

        if (
            !is_array($body) ||
            !isset($body[self::TOKEN_ID]) ||
            !is_string($body[self::TOKEN_ID]) ||
            $body[self::TOKEN_ID] === ''
        ) {
            $this->Error(
                'invalid_request',
                'A verification token is required',
                400
            );
        }

        $this->verification->VerifyToken($body[self::TOKEN_ID]);

        $this->Success([
            'message' => 'E-Mail Address verified successfully'
        ]);
    }
}