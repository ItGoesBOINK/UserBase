<?php

declare(strict_types=1);

namespace UserBase\Service;

use PDOException;
use UserBase\Config\Configuration;
use UserBase\Service\EmailVerificationService;
use UserBase\Service\IEmailSender;
use UserBase\Exception\AccountConflictException;
use UserBase\Exception\ValidationException;
use UserBase\Repository\UserRepository;
use UserBase\Validation\InputValidator;

final class RegistrationService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EmailVerificationService $emailVerification,
        private readonly IEmailSender $emailSender,
        private readonly Configuration $config
    ) { }

    /**
     * Register a new user and return the new user's ID.
     *
     * Email verification and email delivery are handled separately.
     */
    public function Register(
        string $username,
        string $email,
        string $password
    ): string {

        $inUseMsg = "Username or email address is already in use!";
        $inUseErr = new AccountConflictException($inUseMsg);

        $username = trim($username);
        $email = trim($email);

        $this->ValidateUsername($username);
        $this->ValidateEmail($email);
        $this->ValidatePassword($password);

        $usernameNormalized = InputValidator::NormalizeUserName($username);
        $emailNormalized = InputValidator::NormalizeEmail($email);

        // Friendly early checks. Database unique indexes remain
        // responsible for preventing duplicates under concurrency.
        if ($this->users->FindByUsernameNormalized($usernameNormalized) !== null)
            throw $inUseErr;

        if ($this->users->FindByEmailNormalized($emailNormalized) !== null)
            throw $inUseErr;

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {

            $userID = $this->users->Create(
                $username,
                $usernameNormalized,
                $email,
                $emailNormalized,
                $passwordHash
            );
        } catch (PDOException $e) {

            $mysqlErrorCode = $e->errorInfo[1] ?? null;

            if ($mysqlErrorCode === 1062) {
                throw new AccountConflictException($inUseMsg, 0, $e);
            }

            throw $e;
        }

        $token = $this->emailVerification->IssueToken($userID);

        $verificationURL = rtrim($this->config->verificationUrlBase, '/') . '?token=' . rawurlencode($token);

        $this->emailSender->SendVerificationEmail($email, $username, $verificationURL);

        return $userID;
    }

    private function ValidateUsername(string $username): void {
        if (!InputValidator::IsValidUserName($username)) {
            throw new ValidationException('Username must be 8-64 characters, and only contain letters, numbers, periods, dashes, and underscores.');
        }
    }

    private function ValidateEmail(string $email): void {
        if (!InputValidator::IsValidEmail($email)) {
            throw new ValidationException('A valid E-Mail address is required!');
        }
    }

    private function ValidatePassword(string $password): void {
        if (!InputValidator::IsValidPassword($password)) {
            throw new ValidationException('Password must be between 12 and 1024 characters long!');
        }
    }
}

?>