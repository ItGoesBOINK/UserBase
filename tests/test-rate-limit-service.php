<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;
use UserBase\Time\TimeUtility;
use UserBase\Time\SystemClock;
use UserBase\Repository\RateLimitRepository;
use UserBase\Security\RateLimit\RateLimitPolicy;
use UserBase\Service\RateLimitService;

$clock = new SystemClock();
$repo = new RateLimitRepository($pdo);
$service = new RateLimitService($repo, $clock, $config->rateLimitKey);

$policyKey = 'test:service';
$subject = 'rate_limit_test_' . $suffix;
$maxAttempts = 5;
$policy = new RateLimitPolicy($policyKey, $maxAttempts, 900);

// Generate the same subject hash used internally by the service.
// This is needed for inspecting and cleaning up the test events
$subjectHash = hash_hmac(
    'sha256', $subject, $config->rateLimitKey, true
);

try {

    // 1. First attempt should be allowed
    $firstAttemptTime = $clock->Now();
    $firstDecision = $service->Attempt($policy, $subject);

    AssertTest(
        $firstDecision->allowed,
        'First attempt is allowed'
    );

    $d = $maxAttempts - 1;
    AssertTest(
        $firstDecision->remainingAttempts === $d,
        "First attempt leaves {$d} attempts remaining"
    );

    // 2. Consume remaining attempts
    for ($i = 2; $i <= $maxAttempts; $i++) {
        $decision = $service->Attempt($policy, $subject);

        AssertTest(
            $decision->allowed,
            "Attempt {$i}/{$maxAttempts} is allowed"
        );

        AssertTest(
            $decision->remainingAttempts === $maxAttempts - $i,
            "Attempt {$i} returns the correct remaining count"
        );
    }

    // 3. The sixth attempt should be rejected
    $rejectedDecision = $service->Attempt($policy, $subject);

    AssertTest(
        !$rejectedDecision->allowed,
        "Attempt exceeding the limit is rejected"
    );

    AssertTest(
        $rejectedDecision->remainingAttempts === 0,
        "Rejected attempt reports zero remaining attempts"
    );

    AssertTest(
        $rejectedDecision->retryAt !== null,
        "Rejected attempt provides a retry time"
    );

    // 4. Verify the retry time corresponds to the oldest event
    $expectedRetryAt = $firstAttemptTime->modify('+900 seconds');

    AssertTest(
        abs($rejectedDecision->retryAt->getTimestamp() - $expectedRetryAt->getTimestamp()) <= 2,
        "Retry time is based on the oldest attempt"
    );

    // 5. Verify rejected attempts are not recorded
    $attemptCount = $repo->CountSince($policyKey, $subjectHash, $clock->Now()->modify('-900 seconds'));

    AssertTest(
        $attemptCount === $maxAttempts,
        "Rejected attempts are not recorded"
    );

    // 6. Move the oldest event outside the rolling window
    $sql = $pdo->prepare(
        'UPDATE rate_limit_events
         SET occurred_at = :occurred_at
         WHERE policy_key = :policy_key
            AND subject_hash = :subject_hash
         ORDER BY occurred_at ASC
         LIMIT 1'
    );

    $sql->execute([
        'occurred_at' => TimeUtility::FormatTimestamp($clock->Now()->modify('-901 seconds')),
        'policy_key' => $policyKey,
        'subject_hash' => $subjectHash
    ]);

    AssertTest(
        $sql->rowCount() === 1,
        "Oldest event successfully moved outside the rolling window"
    );

    // 7. A new attempt should now be allowed
    $afterExpiration = $service->Attempt($policy, $subject);

    AssertTest(
        $afterExpiration->allowed,
        "Attempt is allowed after the oldest event expires"
    );

    AssertTest(
        $afterExpiration->remainingAttempts === 0,
        "New attempt consumes the newly available attempt"
    );

    // 8. The next attempt should again be rejected
    $rejectedAgain = $service->Attempt($policy, $subject);

    AssertTest(
        !$rejectedAgain->allowed,
        "Rate-Limit is enforced again after the new attempt"
    );

    HandleComplete(__FILE__);

} finally {

    // Remove all test events
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