<?php

declare(strict_types=1);

namespace UserBase\Http;

final class JsonResponse
{
    public static function Success(array $data, int $status = 200): never
    { self::send(['data' => $data], $status); }

    public static function Error(string $errCode, string $message, int $status): never
    { self::send(['error' => ['code' => $errCode, 'message' => $message]], $status); }

    public static function Send(array $response, int $status): never
    {
        http_response_code($status);
        echo json_encode($response, JSON_THROW_ON_ERROR);
        exit;
    }
}

?>