<?php

declare(strict_types=1);

use UserBase\Http\ExceptionHandler;
use UserBase\Http\JsonResponse;

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/src/bootstrap.php';
    JsonResponse::Success(['status' => 'ok', 'service' => 'UserBase']);
} catch (Throwable $e) {
    ExceptionHandler::Handle($e);
}

?>