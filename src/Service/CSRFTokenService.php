<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Config\Configuration;
use UserBase\Security\TokenUtility;
use UserBase\Validation\InputValidator;

final class CSRFTokenService
{
    private readonly string $key;

    public function __construct(
        private Configuration $config,

    ) {
        $this->key = $config->csrfKey;
    }

    public function Generate(string $sessionToken): string
    { return hash_hmac('sha256', $sessionToken, $this->key); }

    public function Validate(
        string $sessionToken,
        string $providedToken
    ): bool {
        if (!InputValidator::IsHexToken($sessionToken)) { return false; }

        $expectedToken = $this->Generate($sessionToken);
        return hash_equals($expectedToken, $providedToken);
    }
}

?>