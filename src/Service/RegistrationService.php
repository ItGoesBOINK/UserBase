<?php

declare(strict_types=1);

namespace UserBase\Service;

use DomainException;
use PDOException;
use UserBase\Repository\UserRepository;

final class RegistrationService
{
    public function __construct(private readonly UserRepository $users) { }

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
        $username = trim($username);
        $email = trim($email);

        $this->ValidateUsername($username);
        $this->ValidateEmail($email);
        $this->ValidatePassword($password);

        $usernameNormalized = strtolower($username);
        $emailNormalized = strtolower($email);

        // Friendly early checks. Database unique indexes remain
        // responsible for preventing duplicates under concurrency.
        if ($this->users->FindByUsernameNormalized($usernameNormalized) !== null)
            throw new DomainException("Username of email address is already in use!");

        if ($this->users->FindByEmailNormalized($emailNormalized) !== null)
            throw new DomainException("Username of email address is already in use!");

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        try {
            return $this->users->Create($username, $usernameNormalized, $email, $emailNormalized, $passwordHash);
        } catch (PDOException $e) {
            // A concurrent registration may have claimed the
            // username or email after the checks above.
            if ($e->getCode() === '23000')
                throw new DomainException("Username or email address is already in use!");

            throw $e;
        }
    }

    private function ValidateUsername(string $username): void {
        if (!preg_match('/\A[a-zA-Z0-9_.-]{8,64}\z/', $username)) {
            throw new DomainException('Username must be 8-64 characters, and only contain letters, numbers, periods, dashes, and underscores.');
        }
    }

    private function ValidateEmail(string $email): void {
        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new DomainException('A valid E-Mail address is required!');
        }
    }

    private function ValidatePassword(string $password): void {
        if (strlen($password) < 12) {
            throw new DomainException('Password must be at least 12 characters long!');
        }

        if (strlen($password) > 1024) {
            throw new DomainException('Password exceeds the permitted length!');
        }
    }
}

?>