<?php

declare(strict_types=1);

namespace UserBase\Http;

use Throwable;
use DomainException;
use InvalidArgumentException;
use UserBase\Exception\EmailDeliveryException;
use UserBase\Exception\AccountConflictException;
use UserBase\Exception\CredentialConflictException;
use UserBase\Exception\ValidationException;
use UserBase\Exception\RateLimitUnavailableException;

final class ExceptionHandler
{
    private static function Err(Throwable $err, string $errCode, int $responseCode): never
    { JsonResponse::Error($errCode, $err->getMessage(), $responseCode); }

    private static function InternalServerError(): never
    {
        JsonResponse::Error(
            'internal_server_error',
            'An Unexpected Server Error Occurred.',
            500
        );
    }

    private static function Log(Throwable $err): void
    {
        error_log(sprintf(
            "UserBase unhandled exception; %s in %s:%d\n%s",
            $err->getMessage(),
            $err->getFile(),
            $err->getLine(),
            $err->getTraceAsString()
        ));
    }

    public static function Handle(Throwable $err): never
    {
        if ($err instanceof ValidationException)
        { self::Err($err, 'validation_failed', 422); }

        if ($err instanceof AccountConflictException)
        { self::Err($err, 'account_conflict', 409); }

        if ($err instanceof CredentialConflictException)
        { self::Err($err, 'credential_conflict', 400); }

        if ($err instanceof DomainException)
        { self::Err($err, 'verification_failed', 400); }

        if ($err instanceof InvalidArgumentException)
        { self::Err($err, 'invalid_request', 400); }

        if ($err instanceof EmailDeliveryException) {
            error_log(sprintf(
                "UserBase E-Mail delivery failed: %s",
                $err->getMessage()
            ));

            JsonResponse::Error(
                'verification_email_failed',
                'Your account was created, but we could not send the verification E-Mail.',
                503
            );
        }

        if ($err instanceof RateLimitUnavailableException) {
            error_log(sprintf(
                "UserBase Rate-Limit Service Unavailable: %s",
                $err->getMessage()
            ));

            JsonResponse::Error(
                'rate_limit_unavailable',
                'Request Processing temporarily unavailable',
                503
            );
        }

        self::Log($err);
        self::InternalServerError();
    }
}

?>