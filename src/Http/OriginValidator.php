<?php

declare(strict_types=1);

namespace UserBase\Http;

use UserBase\Config\Configuration;

final class OriginValidator
{
    public static function Validate(Configuration $config): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        // Allow clients that do not send an Origin header (non-browser)
        if ($origin === null) return;

        if (!OriginPolicy::IsAllowed($config, $origin)) {
            JsonResponse::Error(
                'origin_denied',
                'Origin is not permitted',
                403
            );
        }
    }
}

?>