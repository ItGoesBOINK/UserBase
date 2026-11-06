<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;
use UserBase\Controller\HealthController;


$streamContext = stream_context_create([
    'http' => [
        'method' => 'GET',
        'ignore_errors' => true
    ]
]);

$body = file_get_contents('http://userbase/api/health', false, $context);
$headers = $http_response_header ?? [];

AssertTest(
    $body !== false,
    'Health end-point returns a response'
);

preg_match(
    '/\b(\d{3})\b/',
    $headers[0] ?? '',
    $matches
);

$status = (int)($matches[1] ?? 0);

AssertTest(
    $status === 200,
    'Health end-point returns HTTP 200'
);

$response = json_decode($body, true);

AssertTest(
    json_last_error() === JSON_ERROR_NONE && is_array($response),
    'Health end-point returns valid JSON'
);

AssertTest(
    ($response['data']['status'] ?? null) === 'ok',
    'Health end-point reports "ok"'
);

AssertTest(
    ($response['data']['service'] ?? null) === 'UserBase',
    'Health end-point identifies "UserBase"'
);


HandleComplete(__FILE__);

?>