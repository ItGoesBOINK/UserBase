<?php

declare(strict_types=1);

namespace UserBase\Http;

use Throwable;
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