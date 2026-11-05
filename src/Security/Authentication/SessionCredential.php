<?php

declare(strict_types=1);

namespace UserBase\Security\Authentication;

use InvalidArgumentException;

final readonly class SessionCredential
{
    public function __construct(
        public string $token,
        public SessionCredentialType $type
    ) {
        if ($token === '')
        {
            throw new InvalidArgumentException
            ( 'Session credential token cannot be empty' );
        }
    }
}


?>