<?php

declare(strict_types=1);

namespace UserBase\Controller;
use UserBase\Http\JsonResponse;
use UserBase\Http\RequestContext;


abstract class ControllerBase
{
    abstract public function Execute(RequestContext $context): void;

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