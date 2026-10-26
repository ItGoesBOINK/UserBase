<?php

declare(strict_types=1);

namespace UserBase\Repository;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use UserBase\Validation\InputValidator;
use UserBase\Time\TimeUtility;

final class EmailVerificationTokenRepository
{
    public function __construct(private readonly PDO $connection) {}

    /**
     * Store a verification token hash.
     *
     * The caller must hash the raw token before calling this method.
     * Returns the new token record ID.
     */
    public function Create(
        string $userID,
        string $tokenHash,
        DateTimeImmutable $expiresAt
    ): string {
        $binaryHash = $this->ToBinaryHash($tokenHash);

        $sql = $this->connection->prepare(
            'INSERT INTO email_verification_tokens
            ( user_id, token_hash, expires_at )
            VALUES (:user_id, :token_hash, :expires_at)'
        );

        $sql->bindValue(':user_id', $userID, PDO::PARAM_STR);
        $sql->bindValue(':token_hash', $binaryHash, PDO::PARAM_LOB);
        $sql->bindValue(':expires_at', TimeUtility::FormatTimestamp($expiresAt),
                                        PDO::PARAM_STR);

        $sql->execute();

        return $this->connection->lastInsertId();
    }


    /**
     * Find a token by its SHA-256 hash.
     *
     * The supplied hash must be a 64-character hexadecimal string.
     */
    public function FindByTokenHash(string $tokenHash): ?array
    {
        $binaryHash = $this->ToBinaryHash($tokenHash);

        $sql = $this->connection->prepare(
            'SELECT *
             FROM email_verification_tokens
             WHERE token_hash = :token_hash
             LIMIT 1'
        );

        $sql->bindValue(
            ':token_hash',
            $binaryHash,
            PDO::PARAM_LOB
        );

        $sql->execute();

        $token = $sql->fetch();

        return $token === false ? null : $token;
    }


    /**
     * Invalidate all outstanding verification tokens for a user.
     */
    public function InvalidateOutstandingForUser(
        string $userID,
        DateTimeImmutable $invalidatedAt
    ): void {
        $sql = $this->connection->prepare(
            'UPDATE email_verification_tokens
             SET invalidated_at = :invalidated_at
             WHERE user_id = :user_id
                AND used_at IS NULL
                AND invalidated_at IS NULL'
        );

        $sql->execute([
            'invalidated_at' => TimeUtility::FormatTimestamp($invalidatedAt),
            'user_id' => $userID
        ]);
    }


    /**
     * Mark a token as used.
     */
    public function MarkUsed(
        string $tokenID,
        DateTimeImmutable $usedAt
    ): bool {
        $sql = $this->connection->prepare(
             'UPDATE email_verification_tokens
             SET used_at = :used_at
             WHERE id = :id
                AND used_at IS NULL
                AND invalidated_at IS NULL
                AND expires_at > :consumption_time'
        );

        $formattedUsedAt = TimeUtility::FormatTimestamp($usedAt);

        $sql->execute([
            'used_at' => $formattedUsedAt,
            'id' => $tokenID,
            'consumption_time' => $formattedUsedAt
        ]);

        return $sql->rowCount() === 1;
    }

    private function ToBinaryHash(string $tokenHash): string
    {
        if (!InputValidator::IsValidVerificationToken($tokenHash)) {
            throw new InvalidArgumentException('Token Hash must be a 64-character hexadecimal string!');
        }

        return hex2bin($tokenHash);
    }
}

?>