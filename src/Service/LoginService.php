<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Repository\UserRepository;
use UserBase\Exception\ValidationException;
use UserBase\Validation\InputValidator;
use UserBase\Security\TokenUtility;
use UserBase\Http\ExceptionHandler;
use RuntimeException;
use Throwable;

final class LoginService
{
    private const string DUMMY_PASSWORD_HASH = '9826358923658693479835725892639576237945887273589273795627365298365293658927358823675283653279856923';

    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionService $sessions
    ) {}

    public function Login(string $username, string $password): string
    {
        $usernameNormalized = InputValidator::NormalizeUserName($username);
        $user = $this->users->FindByUsernameNormalized($usernameNormalized);

        $passwordHash = $user['password_hash'] ?? self::DUMMY_PASSWORD_HASH;
        $passwordIsValid = password_verify($password, $passwordHash);

        if ($user === null || !$passwordIsValid) {
            throw new ValidationException('Invalid User-Name or Password');
        }

        if (
            $user === null ||
            !password_verify($password, $user['password_hash'])
        ) {
            throw new ValidationException('Invalid User Name or Password');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            $this->users->UpdatePasswordHash(
                (string)$user['id'],
                $newHash
            );
        }

        if ($user['email_verified_at'] === null) {
            throw new ValidationException('E-Mail Address has not been verified');
        }

        return $this->sessions->Create((string) $user['id']);
    }
}

?>