<?php

declare(strict_types=1);

namespace UserBase\Http;

use UserBase\Config\Configuration;

final class CorsHandler
{
    private const array ALLOWED_METHODS = ['GET', 'POST'];
    private const array ALLOWED_HEADERS = ['content-type'];

    public static function Handle(Configuration $config): void
    {
        header('Vary: Origin');

        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Non-Browser clients may not send an Origin header
        if ($origin === null) return;

        if (!OriginPolicy::IsAllowed($config, $origin)) {
            if ($method === 'OPTIONS') {
                JsonResponse::Error(
                    'cors_origin_denied',
                    'Origin is not permitted',
                    403
                );
            }

            // No CORS permission headers for untrusted origins
            return;
        }

        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');

        if ($method !== 'OPTIONS') return;

        $requestedMethod = strtoupper
        ( $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'] ?? '');

        if (!in_array($requestedMethod, self::ALLOWED_METHODS, true)) {
            JsonResponse::Error(
                'cors_method_denied',
                'Requested method is not permitted',
                403
            );
        }

        $requestedHeaders = strtolower
        ( $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '' );

        $headers = array_filter
        ( array_map('trim', explode(',', $requestedHeaders)) );

        foreach ($headers as $headerName) {
            if (!in_array($headerName, self::ALLOWED_HEADERS, true)) {
                JsonResponse::Error(
                    'cors_header_denied',
                    'Requested header is not permitted',
                    403
                );
            }
        }

        header('Access-Control-Allow-Methods: GET, POST');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');

        http_response_code(204);
        exit;
    }
}

?>