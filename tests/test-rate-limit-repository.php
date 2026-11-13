<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;
use UserBase\Repository\RateLimitRepository;

$repo = new RateLimitRepository($pdo);

$rateKey = $config->rateLimitKey;
$policyKey = 'test:repository';
$subjectHash = hash_hmac('sha256', $suffix, $rateKey, true);

$tfmt = 'Y-m-d H:i:s';
$utc = new DateTimeZone('UTC');
$now = new DateTimeImmutable('now', $utc);
$windowStart = $now->modify('-15 minutes');

try {

    // 0. Create 3 Test Events
    $repo->CreateEvent($policyKey, $subjectHash, $now->modify('-10 minutes'));
    $repo->CreateEvent($policyKey, $subjectHash, $now->modify('-5 minutes'));
    $repo->CreateEvent($policyKey, $subjectHash, $now->modify('-2 minutes'));

    // 1. Verify event count
    $count = $repo->CountSince($policyKey, $subjectHash, $windowStart);
    AssertTest(
        $count === 3,
        'RateLimitRepository counts events within the time window'
    );

    // 2. Verify the oldest event
    $oldest = $repo->FindOldestSince($policyKey, $subjectHash, $windowStart);
    AssertTest(
        $oldest !== null &&
        $oldest->format($tfmt) ===
        $now->modify('-10 minutes')->format($tfmt),
        'RateLimitRepository finds the oldest event'
    );

    // 3. Verify events outside the window are excluded
    $shortWindow = $now->modify('-3 minutes');
    $shortCount = $repo->CountSince($policyKey, $subjectHash, $shortWindow);
    AssertTest(
        $shortCount === 1,
        'Events outside the rolling window are excluded'
    );

    // 4. Verify an unknown subject has no events
    $unknownHash = hash_hmac('sha256', 'unknown_' . $suffix, $rateKey, true);
    $unknownCount = $repo->CountSince($policyKey, $unknownHash, $windowStart);
    AssertTest(
        $unknownCount === 0,
        'Unknown subject returns zero events'
    );

    HandleComplete(__FILE__);

} finally {

    // Remove test events
    $sql = $pdo->prepare(
        'DELETE FROM rate_limit_events
         WHERE policy_key = :policy_key
            AND subject_hash = :subject_hash'
    );

    $sql->execute([
        'policy_key' => $policyKey,
        'subject_hash' => $subjectHash
    ]);
}


?>