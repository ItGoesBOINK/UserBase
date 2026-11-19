<?php

declare(strict_types=1);

namespace UserBase\Bin;

use Throwable;
use RuntimeException;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be executed from the command line.\n");
}

/*
if (($argv[1] ?? '') !== '--confirm') {
    exit("Missing required --confirm argument.\n");
}
*/

$d = dirname(__DIR__);
require_once $d . '/src/bootstrap.php';
//require_once $d . '/tests/Out.php';

//use UserBase\Tests\Out;
use UserBase\Database\DatabaseProxy;

$timestamp = gmdate('Y-m-d\TH:i:s\Z');

try {
    $proxy = new DatabaseProxy($config);
    $connection  = $proxy->GetConnection();

    // Keep database timestamps consistent with application UTC timestamps
    $connection->exec("SET time_zone = '+00:00'");

    // Delete all Rate-Limit Events
    $deleted = $connection->exec("DELETE FROM rate_limit_events");

    if ($deleted === false) {
        throw new RuntimeException('Rate-Limit Clear Query Failed');
    }

    echo "[$timestamp]: Rate-Limit Clear Deleted [{$deleted}] Events.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "[{$timestamp}]: Rate-Limit Clear Failed: [{$e->getMessage()}]\n");
    exit(1);
}

?>