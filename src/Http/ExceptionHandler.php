<?php

declare(strict_types=1);

namespace UserBase\Http;

use Throwable;

final class ExceptionHandler
{
    public static function Handle(Throwable $err): never
    {
        error_log(
            "UserBase unhandled exception; %s in %s:%d\n%s",
            $err->getMessage(),
            $err->getFile(),
            $err->getLine(),
            $err->getTraceAsString()
        );

        JsonResponse::Error(
            'internal_server_error',
            'An Unexpected Server Error Occurred.',
            500
        );
    }
}

?>