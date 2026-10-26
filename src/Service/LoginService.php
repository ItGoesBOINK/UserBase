<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Repository\UserRepository;
use UserBase\Exception\ValidationException;
use UserBase\Validation\InputValidator;
use RuntimeException;
use Throwable;

final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionService $sessions
    ) {}

    public function Login(string $username, string $password): string
    {
        $usernameNormalized = InputValidator::NormalizeUserName($username);
        $user = $this->users->FindByUsernameNormalized($usernameNormalized);

        if (
            $user === null ||
            !password_verify($password, $user['password_hash'])
        ) {
            throw new ValidationException('Invalid User Name or Password');
        }

        if ($user['email_verified_at'] === null) {
            throw new ValidationException('E-Mail Address has not been verified');
        }

        return $this->sessions->Create((string) $user['id']);
    }
}

?>