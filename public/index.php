<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/src/bootstrap.php';
    http_response_code(200);
    echo json_encode(['status' => 'ok', 'service' => 'UserBase']);
} catch (Throwable $e) {
    error_log(sprintf(
        "UserBase startup error; %s in %s:%d\n%s",
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    http_response_code(500);

    echo json_encode(['error' => 'Internal Server Error']);
}



?>