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

        return $this->AttemptMultiple([
            [
                'policy' => $policy,
                'subject' => $subject
            ]
        ])[$policy->key];
    }


    /**
     * Atomically evaluate and record multiple rate-limit policies
     *
     * Each attempt must contain a RateLimitPolicy and its subject
     *
     * @param array<array{
     *      policy: RateLimitPolicy
     *      subject: string
     * }> $attempts
     *
     * @return array<string, RateLimitDecision>
     */
    public function AttemptMultiple(array $attempts): array
    {
        if ($attempts === []) {
            throw new RuntimeException('At least one service request is required');
        }

        $preparedAttempts = [];

        foreach ($attempts as $attempt) {
            $policy = $attempt['policy'] ?? null;
            $subject = $attempt['subject'] ?? null;

            if (
                !$policy instanceof RateLimitPolicy ||
                !is_string($subject) ||
                $subject === ''
            ) {
                throw new RuntimeException('Invalid request attempt');
            }

            if (
                $policy->key === '' ||
                $policy->maxAttempts < 1 ||
                $policy->windowSeconds < 1
            ) {
                throw new RuntimeException('Invalid request policy');
            }

            if (isset($preparedAttempts[$policy->key])) {
                throw new RuntimeException('Duplicate rate-limit key');
            }

            $subjectHash = hash_hmac('sha256', $subject, $this->rateLimitKey, true);

            $preparedAttempts[$policy->key] = [
                'policy' => $policy,
                'subjectHash' => $subjectHash
            ];
        }

        /**
         * Acquire locks in a deterministic order.
         *
         * This prevents two requests needing the same locks
         * in different orders from deadlocking each-other
         */
        uasort(
            $preparedAttempts,
            static function (array $a, array $b): int {
                $aKey = $a['policy']->key . ':' . bin2hex($a['subjectHash']);
                $bKey = $b['policy']->key . ':' . bin2hex($b['subjectHash']);
                return $aKey <=> $bKey;
            }
        );

        $acquiredLocks = [];

        try {

            foreach ($preparedAttempts as $key => $attempt)
            {
                $policy = $attempt['policy'];
                $subjectHash = $attempt['subjectHash'];

                $acquired = $this->rateLimitRepo->AcquireLock(
                    $policy->key,
                    $subjectHash
                );

                if (!$acquired) {
                    throw new RuntimeException('Unable to acquire Rate-Limit Lock');
                }

                $acquiredLocks[] = [
                    'policyKey' => $policy->key,
                    'subjectHash' => $subjectHash
                ];
            }

            $now = $this->clock->Now();
            $decisions = [];

            foreach ($preparedAttempts as $key => $attempt)
            {
                $policy = $attempt['policy'];
                $subjectHash = $attempt['subjectHash'];

                $windowStart = $now->modify('-' . $policy->windowSeconds . ' seconds');

                $attemptCount = $this->rateLimitRepo->CountSince(
                    $policy->key,
                    $subjectHash,
                    $windowStart
                );

                // Check if the limit has already been reached
                if ($attemptCount >= $policy->maxAttempts) {

                    $oldestAttempt = $this->rateLimitRepo->FindOldestSince(
                        $policy->key,
                        $subjectHash,
                        $windowStart
                    );

                    if ($oldestAttempt === null) {
                        throw new RuntimeException('Unable to determine Rate-Limit retry time');
                    }

                    $retryAt = $oldestAttempt->modify('+' . $policy->windowSeconds . ' seconds');

                    $decisions[$policy->key] = new RateLimitDecision(
                        false, 0, $retryAt
                    );

                    continue;
                }

                // This policy has capacity, so record the attempt
                $this->rateLimitRepo->CreateEvent(
                    $policy->key,
                    $subjectHash,
                    $now
                );

                $remainingAttempts = $policy->maxAttempts - $attemptCount - 1;

                $decisions[$policy->key] = new RateLimitDecision(true, $remainingAttempts);
            }

            return $decisions;

        } finally {
            // Release Locks in reverse acquisition order
            foreach (array_reverse($acquiredLocks) as $lock) {
                $this->rateLimitRepo->ReleaseLock(
                    $lock['policyKey'],
                    $lock['subjectHash']
                );
            }
        }
    }
}

?>