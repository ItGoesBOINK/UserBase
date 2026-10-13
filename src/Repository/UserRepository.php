<?php

declare(strict_types=1);

namespace UserBase\Repository;

use DateTimeImmutable;
use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $connection) {}

    /**
     * Create a user and return the new user's ID.
     *
     * The caller is responsible for normalizing the username
     * and email address before calling this method.
     */
    public function Create(
        string $username,
        string $usernameNormalized,
        string $email,
        string $emailNormalized,
        string $passwordHash
    ): string {
        $sql = $this->connection->prepare(
            'INSERT INTO users (
                username,
                username_normalized,
                email,
                email_normalized,
                password_hash
            ) VALUES (
                :username,
                :username_normalized,
                :email,
                :email_normalized,
                :password_hash
            )'
        );

        $sql->execute([
            'username' => $username,
            'username_normalized' => $usernameNormalized,
            'email' => $email,
            'email_normalized' => $emailNormalized,
            'password_hash' => $passwordHash
        ]);

        return $this->connection->lastInsertId();
    }


    /**
     * Create a user and return the new user's ID.
     *
     * The caller is responsible for normalizing the username
     * and email address before calling this method.
     */
    public function FindByID(string $userID): ?array
    {
        $sql = $this->connection->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $sql->execute(['id' => $userID]);

        $user = $sql->fetch();

        return $user === false ? null : $user;
    }


    /**
     * Find a user by their normalized username.
     */
    public function FindByUsernameNormalized(string $usernameNormalized): ?array
    {
        $sql = $this->connection->prepare
        ('SELECT * FROM users WHERE username_normalized = :username_normalized LIMIT 1');

        $sql->execute(['username_normalized' => $usernameNormalized]);

        $user = $sql->fetch();

        return $user === false ? null : $user;
    }


    /**
     * Find a user by their normalized email address.
     */
    public function FindByEmailNormalized(string $emailNormalized): ?array
    {
        $sql = $this->connection->prepare
        ('SELECT * FROM users WHERE email_normalized = :email_normalized LIMIT 1');

        $sql->execute(['email_normalized' => $emailNormalized]);

        $user = $sql->fetch();

        return $user === false ? null : $user;
    }


    /**
     * Set the email verification timestamp.
     */
    public function MarkEmailVerified(string $userID, DateTimeImmutable $verifiedAt): void
    {
        $sql = $this->connection->prepare(
            'UPDATE users
             SET email_verified_at = :verified_at
             WHERE id = :id'
        );

        $sql->execute([
            'verified_at' => $verifiedAt
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d H:i:s.u'),
            'id' => $userID
        ]);
    }
}

?>