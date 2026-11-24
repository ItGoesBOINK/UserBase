<?php

declare(strict_types=1);

namespace UserBase\Service;

use PDO;
use Throwable;
use DomainException;
use UserBase\Exception\EmailDeliveryException;
use UserBase\Time\IClock;
use UserBase\Config\Configuration;
use UserBase\Validation\InputValidator;
use UserBase\Security\TokenUtility;
use UserBase\Mail\IEmailSender;
use UserBase\Repository\UserRepository;
use UserBase\Repository\PasswordResetRepository;

final class PasswordResetRequestService
{
    public function __construct(
        private readonly Configuration $config,
        private readonly PDO $connection,
        private readonly UserRepository $users,
        private readonly PasswordResetRepository $tokens,
        private readonly IClock $clock,
        private readonly IEmailSender $emailSender
    ) {}

    public function RequestReset(string $email): void {
        $email = trim($email);

        if (!InputValidator::IsValidEmail($email))
        { throw new DomainException('Invalid E-Mail Address'); }

        $emailNormalized = InputValidator::NormalizeEmail($email);
        $user = $this->users->FindByEmailNormalized($emailNormalized);

        // Do not reveal whether an account exists
        if ($user === null || $user['email_verified_at'] === null) { return; }

        $userID = (string) $user['id'];
        $now = $this->clock->Now();

        $lifetime = $this->config->passwordResetEmailConfig->tokenLifetime;

        if ($lifetime === null || $lifetime <= 0)
        { throw new DomainException('Password reset token lifetime is not configured'); }

        $expiresAt = $now->modify("+{$lifetime} seconds");
        $rawToken = TokenUtility::NewToken();
        $tokenHash = TokenUtility::TokenToHash($rawToken);

        // Replace out-standing tokens and create the new one atomically
        $this->connection->beginTransaction();

        try {
            $this->tokens->InvalidateForUser($userID, $now);

            $this->tokens->Create(
                $userID,
                $tokenHash,
                $expiresAt,
                $now
            );

            $this->connection->commit();

        } catch (Throwable $e) {
            if ($this->connection->inTransaction())
            { $this->connection->rollBack(); }

            throw $e;
        }

        $resetURL = $this->config->GetPasswordResetPageURL()
            . '?token='
            . rawurlencode($rawToken);

        try {
            $this->emailSender->SendPasswordResetEmail(
                $user['email'],
                $user['username'],
                $resetURL
            );
        } catch (EmailDeliveryException $e) {
            // Keep the response identical for known and unknown accounts
            error_log('Password reset email delivery failed: '. $e->getMessage());
        }
    }
}