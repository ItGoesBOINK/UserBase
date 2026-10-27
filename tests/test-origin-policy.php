<?php

declare(strict_types=1);

use UserBase\Config\Configuration;
use UserBase\Http\OriginPolicy;

require_once dirname(__DIR__) . '/src/bootstrap.php';

$config = new Configuration();

$tests = [
    'configured origin is allowed' => OriginPolicy::IsAllowed($config, 'http://localhost:5173'),
    'un-configured origin is rejected' => OriginPolicy::IsAllowed($config, 'https://evil.example'),
    'origin with a different port is rejected' => OriginPolicy::IsAllowed($config, 'http://localhost:5174'),
    'origin with a different scheme is rejected' => OriginPolicy::IsAllowed($config, 'https://localhost:5173')
];

$failed = 0;

foreach ($tests as $name => $passed) {
    echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
}

if ($failed > 0) throw new RuntimeException('Some OriginPolicy tests failed');
else echo "\nAll OriginPolicy tests passed\n";

?>