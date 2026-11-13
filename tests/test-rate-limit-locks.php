<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/Out.php';

use UserBase\Database\DatabaseProxy;
use UserBase\Repository\RateLimitRepository;
use UserBase\Tests\Out;

// Create a second independent database connection
$databaseB = new DatabaseProxy($config);
$pdoB = $databaseB->GetConnection();

// Create Repositories
$rateLimitsA = new RateLimitRepository($pdo);
$rateLimitsB = new RateLimitRepository($pdoB);

// Generate unique test identifiers
$policyKey = 'test:lock';
$subjectHash = hash('sha256', $suffix);

$lockAcquiredA = false;
$lockAcquiredB = false;

try {

    // 1. Connection A acquires the lock
    $lockAcquiredA = $rateLimitsA->AcquireLock($policyKey, $subjectHash);
    AssertTest($lockAcquiredA, 'Connection-A Acquires Lock');

    // 2. Connection A acquires the lock
    $lockAcquiredB = $rateLimitsB->AcquireLock($policyKey, $subjectHash);
    AssertTest(!$lockAcquiredB, 'Connection-B cannot acquire Lock held by Connection-A');

    // 3. Connection A releases the lock
    $rateLimitsA->ReleaseLock($policyKey, $subjectHash);
    $lockAcquiredA = false;
    Out::Pass('Connection-A Releases the Lock');

    // 4. Connection B can now acquire the lock
    $lockAcquiredB = $rateLimitsB->AcquireLock($policyKey, $subjectHash);
    AssertTest($lockAcquiredB, 'Connection-B acquires Lock released by Connection-A');

    // 3. Connection B releases the lock
    $rateLimitsB->ReleaseLock($policyKey, $subjectHash);
    $lockAcquiredB = false;
    Out::Pass('Connection-B Releases the Lock');

    HandleComplete(__FILE__);

} finally {
    // Ensure any acquired locks are released
    if ($lockAcquiredA)
    { $rateLimitsA->ReleaseLock($policyKey, $subjectHash); }

    if ($lockAcquiredB)
    { $rateLimitsB->ReleaseLock($policyKey, $subjectHash); }
}

?>