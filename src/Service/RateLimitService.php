<?php

declare(strict_types=1);

namespace UserBase\Service;

use RuntimeException;
use UserBase\Time\IClock;
use UserBase\Repository\RateLimitRepository;
use UserBase\Security\RateLimit\RateLimitPolicy;
use UserBase\Security\RateLimit\RateLimitDecision;

final class RateLimitService
{
    public function __construct(
        private readonly RateLimitRepository $rateLimitRepo,
        private readonly IClock $clock,
        private string $rateLimitKey
    ) {}

    /**
     * Atomically evaluate and record a rate-limited event
     */
    public function Attempt(
        RateLimitPolicy $policy,
        string $subject
    ): RateLimitDecision {

        if ($subject === '')
        { throw new RuntimeException('Rate-Limit Subject cannot be empty'); }

        $winSec = $policy->windowSeconds;

        if  (
            $policy->maxAttempts < 1 ||
            $winSec < 1
        ) { throw new RuntimeException('Invalid Rate-Limit Policy'); }

        $pKey = $policy->key;

        $subjectHash = hash_hmac(
            'sha256',
            $subject,
            $this->rateLimitKey,
            true
        );

        $lockAcquired = $this->rateLimitRepo->AcquireLock(
            $pKey,
            $subjectHash
        );

        if (!$lockAcquired)
        { throw new RuntimeException('Unable to acquire Rate-Limit lock'); }

        try {

            $now = $this->clock->Now();

            $windowStart = $now->modify('-' . $winSec . ' seconds');

            $attemptCount = $this->rateLimitRepo->CountSince($pKey, $subjectHash, $windowStart);

            // Check if the limit has already been reached
            if ($attemptCount >= $policy->maxAttempts) {
                $oldestAttempt = $this->rateLimitRepo->FindOldestSince($pKey, $subjectHash, $windowStart);

                if ($oldestAttempt === null) {
                    throw new RuntimeException('Unable to determine Rate-Limit retry time');
                }

                $retryAt = $oldestAttempt->modify('+' . $winSec . ' seconds');

                return new RateLimitDecision(false, 0, $retryAt);
            }

            // Record this attempt before releasing the lock
            $this->rateLimitRepo->CreateEvent($pKey, $subjectHash, $now);

            $remainingAttempts = $policy->maxAttempts - $attemptCount - 1;

            return new RateLimitDecision(true, $remainingAttempts);

        } finally {
            $this->rateLimitRepo->ReleaseLock($pKey, $subjectHash);
        }
    }
}

?>