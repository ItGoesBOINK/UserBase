<?php

declare(strict_types=1);

namespace UserBase\Repository;

use RuntimeException;
use DateTimeZone;
use DateTimeImmutable;
use PDO;
use UserBase\Time\TimeUtility;

final class RateLimitRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {}

    /**
     * Creates a unique name for a database lock
     */
    public function CreateLockName(
        string $policyKey,
        string $subjectHash
    ): string {
        return hash('sha256', $policyKey . ':' . $subjectHash);
    }

    /**
     * Acquire a named lock for a policy and subject
     */
    public function AcquireLock(
        string $policyKey,
        string $subjectHash
    ): bool {

        $lockName = $this->CreateLockName($policyKey, $subjectHash);
        $sql = $this->connection->prepare('SELECT GET_LOCK(:lock_name, 5)');
        $sql->execute(['lock_name' => $lockName]);
        $result = $sql->fetchColumn();

        if ($result === null || $result === false) {
            throw new RuntimeException('Unable to acquire a rate-limit lock');
        }

        return (int)$result === 1;
    }

    /**
     * Release a previously acquired named lock
     */
    public function ReleaseLock(
        string $policyKey,
        string $subjectHash
    ): void {
        $lockName = $this->CreateLockName($policyKey, $subjectHash);
        $sql = $this->connection->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $sql->execute(['lock_name' => $lockName]);
        $result = $sql->fetchColumn();

        if ((int)$result !== 1) {
            throw new RuntimeException('Unable to release rate-limit lock');
        }
    }

    /**
     * Count rate-limit events within a rolling time window
     */
    public function CountSince(
        string $policyKey,
        string $subjectHash,
        DateTimeImmutable $windowStart
    ): int {
        $sql = $this->connection->prepare(
            'SELECT COUNT(*)
             FROM rate_limit_events
             WHERE policy_key = :policy_key
                AND subject_hash = :subject_hash
                AND occurred_at >= :window_start'
        );

        $sql->execute([
            'policy_key' => $policyKey,
            'subject_hash' => $subjectHash,
            "window_start" => TimeUtility::FormatTimestamp($windowStart)
        ]);

        return (int)$sql->fetchColumn();
    }

    /**
     * Find the oldest rate-limit event within a rolling time window
     */
    public function FindOldestSince(
        string $policyKey,
        string $subjectHash,
        DateTimeImmutable $windowStart
    ): ?DateTimeImmutable {
        $sql = $this->connection->prepare(
            'SELECT occurred_at
             FROM rate_limit_events
             WHERE policy_key = :policy_key
                AND subject_hash = :subject_hash
                AND occurred_at >= :window_start
             ORDER BY occurred_at ASC
             LIMIT 1'
        );

        $sql->execute([
            'policy_key' => $policyKey,
            'subject_hash' => $subjectHash,
            'window_start' => TimeUtility::FormatTimestamp($windowStart)
        ]);

        $result = $sql->fetchColumn();

        return $result === false
            ? null
            : new DateTimeImmutable($result, new DateTimeZone('UTC'));
    }

    /**
     * Record a rate-limited event
     */
    public function CreateEvent(
        string $policyKey,
        string $subjectHash,
        DateTimeImmutable $occurredAt
    ): void {
        $sql = $this->connection->prepare(
            'INSERT INTO rate_limit_events
             (policy_key, subject_hash, occurred_at)
             VALUES
             (:policy_key, :subject_hash, :occurred_at)'
        );

        $sql->execute([
            'policy_key' => $policyKey,
            'subject_hash' => $subjectHash,
            'occurred_at' => TimeUtility::FormatTimestamp($occurredAt)
        ]);
    }
}

?>