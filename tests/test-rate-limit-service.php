<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/Out.php';

use RuntimeException;
use UserBase\Tests\Out;
use UserBase\Time\TimeUtility;
use UserBase\Time\SystemClock;
use UserBase\Database\DatabaseProxy;
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

    // ------ Multiple Policy Tests --------------------------------
    $now = $clock->Now();
    $ipPolicy = new RateLimitPolicy('test:multi:ip', $maxAttempts, 900);
    $accountPolicy = new RateLimitPolicy('test:multi:account', $maxAttempts, 900);

    $ipSubject = "multi_ip_" . $suffix;
    $accountSubject = "multi_account_" . $suffix;
    $rateKey = $config->rateLimitKey;

    $ipHash = hash_hmac('sha256', $ipSubject, $rateKey, true);
    $accountHash = hash_hmac('sha256', $accountSubject, $rateKey, true);

    $windowStart = $now->modify('-900 seconds');

    // 9. Both Policies Allow
    $decisions = $service->AttemptMultiple([
        $ipPolicy->key => [
            'policy' => $ipPolicy,
            'subject' => $ipSubject
        ],
        $accountPolicy->key => [
            'policy' => $accountPolicy,
            'subject' => $accountSubject
        ]
    ]);

    AssertTest(
        $decisions[$ipPolicy->key]->allowed,
        'Multiple Policies: IP policy allows request'
    );

    AssertTest(
        $decisions[$accountPolicy->key]->allowed,
        'Multiple Policies: Account policy allows request'
    );

    $r = $maxAttempts - 1;
    AssertTest(
        $decisions[$ipPolicy->key]->remainingAttempts === $r,
        "Multiple Policies: IP policy has {$r} attempts remaining"
    );

    AssertTest(
        $decisions[$accountPolicy->key]->remainingAttempts === $r,
        "Multiple Policies: Account policy has {$r} attempts remaining"
    );

    AssertTest(
        $repo->CountSince($ipPolicy->key, $ipHash, $windowStart) === 1,
        'Multiple Policies: IP Event was recorded'
    );

    AssertTest(
        $repo->CountSince($accountPolicy->key, $accountHash, $windowStart) === 1,
        'Multiple Policies: Account Event was recorded'
    );


    // 10. One Policy Allows while another Policy Denies
    $ipPolicy = new RateLimitPolicy(
        'test:multi:partial:ip',
        $maxAttempts, 900
    );

    $accountPolicy = new RateLimitPolicy(
        'test:multi:partial:account',
        $maxAttempts, 900
    );

    $ipSubject = 'partial_ip_' . $suffix;
    $accountSubject = 'partial_account_' . $suffix;

    $ipHash = hash_hmac('sha256', $ipSubject, $rateKey, true);
    $accountHash = hash_hmac('sha256', $accountSubject, $rateKey, true);

    // Exhaust the account policy
    for ($i = 0; $i < $maxAttempts; $i++) {
        $repo->CreateEvent($accountPolicy->key, $accountHash, $now);
    }

    $decisions = $service->AttemptMultiple([
        $ipPolicy->key => [
            'policy' => $ipPolicy,
            'subject' => $ipSubject
        ],
        $accountPolicy->key => [
            'policy' => $accountPolicy,
            'subject' => $accountSubject
        ]
    ]);

    AssertTest(
        $decisions[$ipPolicy->key]->allowed,
        'Multiple Policies: IP policy allows when account policy is exhausted'
    );

    AssertTest(
        !$decisions[$accountPolicy->key]->allowed,
        'Multiple Policies: Account policy denies when exhausted'
    );

    AssertTest(
        $repo->CountSince($ipPolicy->key, $ipHash, $windowStart) === 1,
        'Multiple Policies: Allowed policy recorded its event'
    );

    AssertTest(
        $repo->CountSince($accountPolicy->key, $accountHash, $windowStart) === $maxAttempts,
        'Multiple Policies: denied account policy does not record another event'
    );

    AssertTest(
        $decisions[$accountPolicy->key]->retryAt !== null,
        'Multiple Policies: denied account policy provides a retry time'
    );

    // 11. Both Policies Deny
    $ipPolicy = new RateLimitPolicy(
        'test:multi:denied:ip',
        $maxAttempts, 900
    );

    $accountPolicy = new RateLimitPolicy(
        'test:multi:denied:account',
        $maxAttempts, 900
    );

    $ipSubject = 'denied_ip_' . $suffix;
    $accountSubject = 'denied_account_' . $suffix;

    $ipHash = hash_hmac('sha256', $ipSubject, $rateKey, true);
    $accountHash = hash_hmac('sha256', $accountSubject, $rateKey, true);

    // Exhaust the account policy
    for ($i = 0; $i < $maxAttempts; $i++) {
        $repo->CreateEvent($ipPolicy->key, $ipHash, $now);
        $repo->CreateEvent($accountPolicy->key, $accountHash, $now);
    }

    $decisions = $service->AttemptMultiple([
        $ipPolicy->key => [
            'policy' => $ipPolicy,
            'subject' => $ipSubject
        ],
        $accountPolicy->key => [
            'policy' => $accountPolicy,
            'subject' => $accountSubject
        ]
    ]);

    AssertTest(
        !$decisions[$ipPolicy->key]->allowed,
        'Multiple Policies: Exhausted IP policy denies request'
    );

    AssertTest(
        !$decisions[$accountPolicy->key]->allowed,
        'Multiple Policies: Exhausted Account policy denies request'
    );

    AssertTest(
        $repo->CountSince($ipPolicy->key, $ipHash, $windowStart) === $maxAttempts,
        'Multiple Policies: denied IP policy does not record another event'
    );

    AssertTest(
        $repo->CountSince($accountPolicy->key, $accountHash, $windowStart) === $maxAttempts,
        'Multiple Policies: denied account policy also does not record another event'
    );

    AssertTest(
        !$decisions[$ipPolicy->key]->allowed,
        'Multiple Policies: Exhausted IP policy denies request'
    );

    AssertTest(
        !$decisions[$accountPolicy->key]->allowed,
        'Multiple Policies: Exhausted Account policy denies request'
    );


    // 12. Concurrency / Lock-Contention Test:
    // Successfully Acquired Locks get released
    // when subsequent Lock requests fail
    $databaseB = new DatabaseProxy($config);
    $pdoB = $databaseB->GetConnection();
    $repoB = new RateLimitRepository($pdoB);

    $ipPolicy = new RateLimitPolicy(
        'test:multi:lock:a',
        $maxAttempts, 900
    );

    $accountPolicy = new RateLimitPolicy(
        'test:multi:lock:z',
        $maxAttempts, 900
    );

    $ipSubject = 'lock_ip_' . $suffix;
    $accountSubject = 'lock_account_' . $suffix;

    $ipHash = hash_hmac('sha256', $ipSubject, $rateKey, true);
    $accountHash = hash_hmac('sha256', $accountSubject, $rateKey, true);

    // Connect B acquires the second policy's lock
    $accountLockAcquired = $repoB->AcquireLock(
        $accountPolicy->key, $accountHash
    );

    AssertTest(
        $accountLockAcquired,
        'Multiple Policies: second connection acquires lock'
    );

    try {

        $exceptionThrown = false;

        try {

            // Connection A acquires the IP lock, then times out
            // while trying to acquire the account lock
            $decisions = $service->AttemptMultiple([
                [
                    'policy' => $ipPolicy,
                    'subject' => $ipSubject
                ],
                [
                    'policy' => $accountPolicy,
                    'subject' => $accountSubject
                ]
            ]);

        } catch (RuntimeException $e) {
            $exceptionThrown = true;
        }

        AssertTest(
            $exceptionThrown,
            'Multiple Policies: lock contention causes as exception'
        );

        // Connection B should now be able to acquire the IP lock.
        // This proves connection A released it after the timeout.
        $ipLockAcquired = $repoB->AcquireLock($ipPolicy->key, $ipHash);

        AssertTest(
            $ipLockAcquired,
            'Multiple Policies: previously acquired IP lock was released'
        );

        if ($ipLockAcquired) {
            $repoB->ReleaseLock($ipPolicy->key, $ipHash);
        }

    } finally {
        $repoB->ReleaseLock($accountPolicy->key, $accountHash);
    }

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