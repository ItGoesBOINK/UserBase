<?php

declare(strict_types=1);

namespace UserBase\Controller;
use UserBase\Http\JsonResponse;


abstract class ControllerBase
{
    abstract public function Execute(): void;

    protected function Success(
        array $data,
        int $status = 200
    ): never {
        JsonResponse::Success($data, $status);
    }

    protected function Error(
        string $errorCode,
        string $message,
        int $status
    ): never {
        JsonResponse::Error($errorCode, $message, $status);
    }
}


?>