<?php

declare(strict_types=1);

namespace UserBase\Http;

final class APIErrors
{
    public const string CODE__AUTH_REQUIRED = 'AUTHENTICATION_REQUIRED';
    public const string DESC__AUTH_REQUIRED = 'Authentication is required to access this resource';

    public const string CODE__CSRF_INVALID = 'CSRF_TOKEN_INVALID';
    public const string DESC__CSRF_INVALID = 'The CSRF Token is missing or invalid';
}

?>