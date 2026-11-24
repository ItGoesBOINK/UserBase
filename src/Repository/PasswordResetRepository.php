<?php

declare(strict_types=1);

namespace UserBase\Repository;

use InvalidArgumentException;
use DateTimeImmutable;
use PDO;
use UserBase\Time\TimeUtility;
use UserBase\Security\TokenUtility;

final class PasswordResetRepository
{
    public function __construct(private readonly PDO $connection) {}

    private function ToBinaryHash(string $tokenHash): string {
        if (!TokenUtility::IsValidHash($tokenHash)) {
            throw new InvalidArgumentException('Invalid Token Hash');
        }
        return TokenUtility::HashToBinary($tokenHash);
    }

    public function Create(
        string $userID,
        string $tokenHash,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt
    ): string {
        $binaryHash = $this->ToBinaryHash($tokenHash);
        $sql = $this->connection->prepare(
            'INSERT INTO password_reset_tokens
                (user_id, token_hash, expires_at, created_at)
             VALUES
                (:user_id, :token_hash, :expires_at, :created_at)'
        );

        $sql->execute([
            'user_id' => $userID,
            'token_hash' => $binaryHash,
            'expires_at' => TimeUtility::FormatTimestamp($expiresAt),
            'created_at' => TimeUtility::FormatTimestamp($createdAt)
        ]);

        return $this->connection->lastInsertId();
    }

    public function FindValidByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?array {
        $binaryHash = $this->ToBinaryHash($tokenHash);
        $sql = $this->connection->prepare(
            'SELECT id, user_id, expires_at
             FROM password_reset_tokens
             WHERE token_hash = :token_hash
                AND consumed_at IS NULL
                AND expires_at > :now
             LIMIT 1'
        );

        $sql->execute([
            'token_hash' => $binaryHash,
            'now' => TimeUtility::FormatTimestamp($now)
        ]);

        $token = $sql->fetch();

        return $token === false ? null : $token;
    }

    public function Consume(
        string $tokenHash,
        DateTimeImmutable $now
    ): bool {
        $binaryHash = $this->ToBinaryHash($tokenHash);
        $sql = $this->connection->prepare(
            'UPDATE password_reset_tokens
             SET consumed_at = :consumed_at
             WHERE token_hash = :token_hash
                AND consumed_at IS NULL
                AND expires_at > :now'
        );

        $timestamp = TimeUtility::FormatTimestamp($now);

        $sql->execute([
            'consumed_at' => $timestamp,
            'token_hash' => $binaryHash,
            'now' => $timestamp
        ]);

        return $sql->rowCount() === 1;
    }

    public function InvalidateForUser(
        string $userID,
        DateTimeImmutable $now
    ): int {
        $sql = $this->connection->prepare(
            'UPDATE password_reset_tokens
             SET consumed_at = :consumed_at
             WHERE user_id = :user_id
                AND consumed_at IS NULL'
        );

        $sql->execute([
            'consumed_at' => TimeUtility::FormatTimestamp($now),
            'user_id' => $userID
        ]);

        return $sql->rowCount();
    }
}