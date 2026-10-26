<?php

declare(strict_types=1);

namespace UserBase\Security;

final class TokenUtility
{
    public static function NewToken(): string
    { return bin2hex(random_bytes(32)); }

    public static function TokenToHash(string $token): string
    { return hash('sha256', $token); }

    public static function HashToBinary(string $hash): string
    { return hex2bin($hash); }
}

?>