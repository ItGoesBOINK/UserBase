<?php

declare(strict_types=1);

namespace UserBase\Service;

use DateTimeZone;
use DateTimeImmutable;
use DomainException;
use Throwable;
use UserBase\Repository\EmailVerificationTokenRepository;
use UserBase\Repository\UserRepository;
use UserBase\Time\IClock;

final class EmailVerificationService
{
    public function __construct(
        private readonly \PDO $connection,
        private readonly UserRepository $users,
        private readonly EmailVerificationTokenRepository $tokens,
        private readonly IClock $clock
    ) {}

    private function NotifyInvalidOrExpired()
        { throw new DomainException('Verification link is invalid or has expired!'); }
    private function NotifyAlreadyVerified()
        { throw new DomainException('This e-mail address has already been verified!'); }
    private function NotifyUserNotFound()
        { throw new DomainException('User Account was not found!'); }

    public function IssueToken(string $userID): string
    {
        $user = $this->users->FindByID($userID);

        if ($user === null) $this->NotifyUserNotFound();

        if ($user['email_verified_at'] !== null) $this->NotifyAlreadyVerified();

        $now = $this->clock->Now();
        $expiresAt = $now->modify('+24 hours');

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $this->connection->beginTransaction();

        try {
            $this->tokens->InvalidateOutstandingForUser($userID, $now);
            $this->tokens->Create($userID, $tokenHash, $expiresAt);
            $this->connection->commit();
            return $rawToken;
        } catch (Throwable $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $e;
        }
    }

    public function VerifyToken(string $rawToken): void
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/i', $rawToken)) $this->NotifyInvalidOrExpired();

        $tokenHash = hash('sha256', $rawToken);
        $now = $this->clock->Now();

        $this->connection->beginTransaction();

        try {
            $token = $this->tokens->FindByTokenHash($tokenHash);

            if ($token === null) $this->NotifyInvalidOrExpired();

            $expiresAt = new DateTimeImmutable($token['expires_at'], new DateTimeZone('UTC'));

            if (
                $token['used_at'] !== null
                || $token['invalidated_at'] !== null
                || $expiresAt <= $now
            ) $this->NotifyInvalidOrExpired();

            $userID = (string) $token['user_id'];
            $user = $this->users->FindByID($userID);

            if ($user === null) $this->NotifyInvalidOrExpired();
            if ($user['email_verified_at'] !== null) $this->NotifyAlreadyVerified();

            $consumed = $this->tokens->MarkUsed((string) $token['id'], $now);

            if (!$consumed) $this->NotifyInvalidOrExpired();

            $this->users->MarkEmailVerified($userID, $now);
            $this->connection->commit();

        } catch (Throwable $e) {
            if ($this->connection->inTransaction())
                $this->connection->rollBack();

            throw $e;
        }
    }
}

?>