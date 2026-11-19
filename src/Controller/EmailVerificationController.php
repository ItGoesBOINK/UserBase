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
        $body = $context->body;

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