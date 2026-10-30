<?php

declare(strict_types=1);

namespace UserBase\Security;

use InvalidArgumentException;
use UserBase\Validation\InputValidator;

final class TokenUtility
{
    public static function NewToken(): string
    { return bin2hex(random_bytes(32)); }

    public static function TokenToHash(string $token): string
    { return hash('sha256', $token); }

    public static function HashToBinary(string $hash): string
    {
        if (!self::IsValidHash($hash)) {
            throw new InvalidArgumentException('Invalid SHA-256 hash');
        }
        return hex2bin($hash);
    }

    public static function IsValidHash(string $hash): bool
    { return InputValidator::IsValidSessionToken; }
}

?>