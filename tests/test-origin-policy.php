<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/Out.php';

use UserBase\Config\Configuration;
use UserBase\Http\OriginPolicy;
use UserBase\Tests\Out;


//$config = new Configuration();
$allowed = $config->corsAllowedOrigins[0];
/*
var_dump([
    'configured' => $allowed,
    'evil' => in_array('https://evil.example', $config->corsAllowedOrigins, true),
    'different_port' => in_array('http://localhost:5174', $config->corsAllowedOrigins, true),
    'different_scheme' => in_array('https://localhost:5173', $config->corsAllowedOrigins, true)
]);
*/

/*
$tests = [
    'configured origin is allowed' =>
        OriginPolicy::IsAllowed($config, 'http://localhost:5173'),
    'un-configured origin is rejected' =>
        OriginPolicy::IsAllowed($config, 'https://evil.example'),
    'origin with a different port is rejected' =>
        OriginPolicy::IsAllowed($config, 'http://localhost:5174'),
    'origin with a different scheme is rejected' =>
        OriginPolicy::IsAllowed($config, 'https://localhost:5173')
];
*/

$tests = [
    'configured origin is allowed' =>
        OriginPolicy::IsAllowed($config, 'http://localhost:5173'),
    'un-configured origin is rejected' =>
        !OriginPolicy::IsAllowed($config, 'https://evil.example'),
    'origin with a different port is rejected' =>
        !OriginPolicy::IsAllowed($config, 'http://localhost:5174'),
    'origin with a different scheme is rejected' =>
        !OriginPolicy::IsAllowed($config, 'https://localhost:5173')
];

$failed = 0;

foreach ($tests as $name => $passed) {
    //echo ($passed ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    if ($passed) {
        echo Out::GetPass($name);
    } else {
        echo Out::GetFail($name);
        $failed++;
    }

}

if ($failed > 0) throw new RuntimeException('Some OriginPolicy tests failed');
else HandleComplete(__FILE__);

?>