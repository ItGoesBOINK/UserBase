<?php

declare(strict_types=1);

namespace UserBase\Http;

use Throwable;
use DomainException;
use UserBase\Exception\EmailDeliveryException;
use UserBase\Exception\AccountConflictException;
use UserBase\Exception\ValidationException;

final class ExceptionHandler
{
    public static function Handle(Throwable $err): never
    {
        if ($err instanceof ValidationException) {
            JsonResponse::Error(
                'validation_failed',
                $err->getMessage(),
                422
            );
        }

        if ($err instanceof AccountConflictException) {
            JsonResponse::Error(
                'account_conflict',
                $err->getMessage(),
                409
            );
        }

        if ($err instanceof DomainException)
        {
            JsonResponse::Error(
                'verification_failed',
                $err->getMessage(),
                400
            );
        }

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

        error_log(sprintf(
            "UserBase unhandled exception; %s in %s:%d\n%s",
            $err->getMessage(),
            $err->getFile(),
            $err->getLine(),
            $err->getTraceAsString()
        ));

        JsonResponse::Error(
            'internal_server_error',
            'An Unexpected Server Error Occurred.',
            500
        );
    }
}

?>