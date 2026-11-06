<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Repository\UserRepository;
use UserBase\Exception\ValidationException;
use UserBase\Validation\InputValidator;
use UserBase\Security\TokenUtility;
use UserBase\Security\Authentication\SessionGrant;
use UserBase\Http\ExceptionHandler;
use RuntimeException;
use Throwable;

final class LoginService
{
    private const string DUMMY_PASSWORD_HASH = '$2y$12$FpX.bnt/xlWym5VqYeWTLO3VMLEhkuyf21tIv4P94q9zkknZCJQlW';

    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionService $sessions
    ) {}

    public function Login(string $username, string $password): SessionGrant
    {
        $usernameNormalized = InputValidator::NormalizeUserName($username);
        $user = $this->users->FindByUsernameNormalized($usernameNormalized);

        $passwordHash = $user['password_hash'] ?? self::DUMMY_PASSWORD_HASH;
        $passwordIsValid = password_verify($password, $passwordHash);

        if ($user === null || !$passwordIsValid) {
            throw new ValidationException('Invalid User-Name or Password');
        }

        if ($user['email_verified_at'] === null) {
            throw new ValidationException('E-Mail Address has not been verified');
        }

        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);

            $this->users->UpdatePasswordHash(
                (string)$user['id'],
                $newHash
            );
        }

        return $this->sessions->Create((string) $user['id']);
    }
}

?>