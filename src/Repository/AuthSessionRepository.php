<?php

declare(strict_types=1);

namespace UserBase\Repository;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use UserBase\Security\TokenUtility;
use UserBase\Time\TimeUtility;

final class AuthSessionRepository
{
    public function __construct(
        private readonly PDO $connection
    ) {}

    /**
     * Store a new authenticated session.
     * The database receives only the SHA-256 token hash.
     */
    public function Create(
        string $userID,
        string $tokenHash,
        DateTimeImmutable $expiresAt
    ): string {

        $sql = $this->connection->prepare(
            'INSERT INTO auth_sessions
            (user_id, token_hash, expires_at)
            VALUES
            (:user_id, :token_hash, :expires_at)'
        );

        $sql->execute([
            'user_id' => $userID,
            'token_hash' => TokenUtility::HashToBinary($tokenHash),
            'expires_at' => TimeUtility::FormatTimestamp($expiresAt)
        ]);

        return $this->connection->lastInsertId();
    }

    /**
     * Revoke an authenticated session.
     */
    public function Revoke(
        string $tokenHash,
        DateTimeImmutable $revokedAt
    ): bool {
        $sql = $this->connection->prepare(
            'UPDATE auth_sessions
             SET revoked_at = :revoked_at
             WHERE token_hash = :token_hash
                AND revoked_at IS NULL'
        );

        $sql->execute([
            'revoked_at' => TimeUtility::FormatTimestamp($revokedAt),
            'token_hash' => TokenUtility::HashToBinary($tokenHash)
        ]);

        return $sql->rowCount() === 1;
    }


    /**
     * Find an active session by its token hash.
     */
    public function FindActiveByTokenHash(
        string $tokenHash,
        DateTimeImmutable $now
    ): ?array {
        $sql = $this->connection->prepare(
            'SELECT id, user_id, expires_at
             FROM auth_sessions
             WHERE token_hash = :token_hash
                AND revoked_at IS NULL
                AND expires_at > :now
             LIMIT 1'
        );

        $sql->execute([
            'token_hash' => TokenUtility::HashToBinary($tokenHash),
            'now' => TimeUtility::FormatTimestamp($now)
        ]);

        $session = $sql->fetch();

        return $session === false ? null : $session;
    }
}


?>