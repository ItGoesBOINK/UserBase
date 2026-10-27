<?php

declare(strict_types=1);

namespace UserBase\Http;

use UserBase\Config\Configuration;

final class OriginPolicy
{
    public static function IsAllowed(
        Configuration $config,
        string $origin
    ): bool {
        return in_array(
            $origin,
            $config->corsAllowedOrigins,
            true
        );
    }
}