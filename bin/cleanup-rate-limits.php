<?php

declare(strict_types=1);

namespace UserBase\Bin;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be executed from the command line.\n");
}

$d = dirname(__DIR__);
//echo "\n\ndirname(__DIR__) == {$d}\n\n";
require_once $d . '/src/bootstrap.php';
//require_once $d . '/tests/Out.php';

//use UserBase\Tests\Out;
use UserBase\Database\DatabaseProxy;

try {

    $proxy = new DatabaseProxy($config);
    $connection = $proxy->GetConnection();

    // Keep database timestamps consistent with application UTC timestamps
    $connection->exec("SET time_zone = '+00:00'");

    // Delete expired events in manageable batches
    $batchSize = $config->rateLimitCleanup_CRONBatchSize;
    $totalDeleted = 0;

    do {
        $deleted = $connection->exec(
            "DELETE FROM rate_limit_events
             WHERE occurred_at < UTC_TIMESTAMP(6) - INTERVAL 2 DAY
             LIMIT {$batchSize}"
        );

        if ($deleted === false) {
            throw new RuntimeException('Rate-Limit Clean-Up query failed!');
        }

        $totalDeleted += $deleted;

    } while ($deleted === $batchSize);

    $timestamp - gmdate('Y-m-d\TH:i:s\Z');

    echo "[{$timestamp}]: Rate-Limit Clean-Up Deleted {$totalDeleted} expired events.\n";

} catch (Throwable $e) {
    fwrite(STDERR, "[{$timestamp}]: Rate-Limit Cleanup Failed: [{$e->getMessage()}]\n");
    exit(1);
}

?>