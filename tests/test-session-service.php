<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';

use UserBase\Repository\AuthSessionRepository;
use UserBase\Repository\UserRepository;
use UserBase\Service\SessionService;
use UserBase\Tests\TestClock;

$users = new UserRepository($pdo);
$sessions = new AuthSessionRepository($pdo);

try {
    // 1. Create a test user
    $userID = $users->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    $clock = new TestClock(
        new DateTimeImmutable(
            '2026-01-15 12:00:00',
            new DateTimeZone('UTC')
        )
    );

    $service = new SessionService($sessions, $clock);

    // 2. Create a session
    $rawToken = $service->Create($userID);

    AssertTest(
        IsValidToken($rawToken),
        'Create returns a valid 64-character session token'
    );

    // 3. Confirm the database stores only the token hash
    $tokenHash = hash('sha256', $rawToken);

    $sql = $pdo->prepare(
        'SELECT token_hash FROM auth_sessions WHERE user_id = :user_id'
    );

    $sql->execute(['user_id' => $userID]);

    $storedHash = $sql->fetchColumn();

    AssertTest(
        $storedHash === hex2bin($tokenHash),
        'Database stores the SHA-256 token hash'
    );

    AssertTest(
        $storedHash !== $rawToken,
        'Database does not store the raw session token'
    );

    // 4. Validate the new session
    $session = $service->Validate($rawToken);

    AssertTest(
        $session !== null &&
        (string) $session['user_id'] === $userID,
        'New session validates and returns the correct user ID'
    );

    // 5. Reject malformed and unknown tokens
    AssertTest(
        $service->Validate('invalid-token') === null,
        'Malformed token (invalid length) is rejected'
    );

    AssertTest(
        $service->Validate(str_repeat('a', 64)) === null,
        'Malformed token (invalid hash value) is rejected'
    );

    // Revoke the session
    $service->Revoke($rawToken);

    AssertTest(
        $service->Validate($rawToken) === null,
        'Revoked session is rejected'
    );

    // 7. Create another session and advance beyond expiration
    $expiringToken = $service->Create($userID);

    AssertTest(
        $service->Validate($expiringToken) !== null,
        'Second session is initially valid'
    );

    $clock->Advance('+25 hours');

    AssertTest(
        $service->Validate($expiringToken) === null,
        'Expired session is rejected'
    );

    echo "\nAll SessionService tests passed.\n";

} finally {
    RemoveTestUser($pdo, $userID);
}

?>