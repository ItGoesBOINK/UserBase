<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Config\Configuration;
use UserBase\Validation\InputValidator;

final class CSRFTokenService
{
    public function __construct(
        private Configuration $config
    ) {}

    public function Generate(string $sessionToken): string
    { return hash_hmac('sha256', $sessionToken, $this->config->csrfKey); }

    public function Validate(
        string $sessionToken,
        string $providedToken
    ): bool {
        if (!InputValidator::IsHexToken($sessionToken))  { return false; }
        if (!InputValidator::IsHexToken($providedToken)) { return false; }

        $expectedToken = $this->Generate($sessionToken);
        return hash_equals($expectedToken, $providedToken);
    }
}

?>