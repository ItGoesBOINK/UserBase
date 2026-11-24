<?php

declare(strict_types=1);

namespace UserBase\Service;

use Throwable;
use DomainException;
use RuntimeException;
use PDO;
use UserBase\Time\IClock;
use UserBase\Repository\UserRepository;
use UserBase\Repository\PasswordResetRepository;
use UserBase\Service\SessionService;
use UserBase\Security\TokenUtility;
use UserBase\Validation\InputValidator;

final class PasswordResetService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly UserRepository $userRepo,
        private readonly PasswordResetRepository $tokenRepo,
        private readonly SessionService $sessionService,
        private readonly IClock $clock
    ) {}

    public function ResetPassword(
        string $rawToken,
        string $newPassword
    ): void {

        if (!InputValidator::IsValidVerificationToken($rawToken))
        { throw new DomainException('Invalid Password Reset Token'); }

        if (!InputValidator::IsValidPassword($newPassword))
        { throw new DomainException('Invalid Password'); }

        $now = $this->clock->Now();
        $tokenHash = TokenUtility::TokenToHash($rawToken);

        $token = $this->tokenRepo->FindValidByTokenHash(
            $tokenHash, $now
        );

        if ($token === null) {
            throw new DomainException('Invalid or Expired Password Reset Token');
        }

        $this->connection->beginTransaction();

        try {
            $consumed = $this->tokenRepo->Consume($tokenHash, $now);

            if (!$consumed) {
                throw new DomainException('Invalid or Expired Password Reset Token');
            }

            $userID = (string) $token['user_id'];
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            if ($passwordHash === false) {
                throw new RuntimeException('Unable to hash password');
            }

            $this->userRepo->UpdatePasswordHash($userID, $passwordHash);

            $this->sessionService->RevokeAllForUser($userID);

            $this->connection->commit();

        } catch (Throwable $e) {
            if ($this->connection->inTransaction())
            { $this->connection->rollBack(); }

            throw $e;
        }
    }
}