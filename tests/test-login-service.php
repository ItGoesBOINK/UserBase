<?php

declare(strict_types=1);

namespace UserBase\Tests;

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';

use Throwable;
use DateTimeImmutable;
use DateTimeZone;
use UserBase\Repository\AuthSessionRepository;
use UserBase\Repository\UserRepository;
use UserBase\Service\LoginService;
use UserBase\Service\SessionService;
use Userbase\Tests\TestClock;

$users = new UserRepository($pdo);
$sessionRepository = new AuthSessionRepository($pdo);

$clock = new TestClock(
    new DateTimeImmutable(
        '2026-01-15 12:00:00',
        new DateTimeZone('UTC')
    )
);

$sessions = new SessionService($sessionRepository, $clock);
$login = new LoginService($users, $sessions);

try {
    // 1. Create a test user.  E-Mail is initially unverified
    $userID = $users->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    // Helper to count sessions belonging to this test user
    $countSessions = function () use ($pdo, $userID): int {
        $sql = $pdo->prepare(
            'SELECT COUNT(*) FROM auth_sessions WHERE user_id = :user_id'
        );

        $sql->execute(['user_id' => $userID]);

        return (int) $sql->fetchColumn();
    };

    // 2. Reject login while the email is unverified
    AssertDomainException(
        fn () => $login->Login($username, $password),
        'Login rejects an unverified E-Mail Address'
    );

    AssertTest(
        $countSessions() === 0,
        'Unverified login does not create a session'
    );


    // 3. Verify the user's E-Mail so successful login is possible
    $users->MarkEmailVerified($userID, $clock->Now());

    // 4. Successful login returns a valid session token
    $rawToken = $login->Login($username, $password);

    AssertTest(
        IsValidToken($rawToken),
        'Successful login returns a valid session token'
    );

    $session = $sessions->Validate($rawToken);

    AssertTest(
        $session !== null &&
        (string) $session['user_id'] === $userID,
        'Login creates a valid session for the correct user'
    );

    AssertTest(
        $countSessions() === 1,
        'Successful login creates exactly one session'
    );


    // 5. Reject an incorrect password
    AssertDomainException(
        fn () => $login->Login($username, 'incorrect-password'),
        'Login rejects an incorrect password'
    );

    AssertTest(
        $countSessions() === 1,
        'Incorrect password does not create another session'
    );


    // 6. Reject and unknown user name
    AssertDomainException(
        fn () => $login->Login('_unknown_' . $suffix, $password),
        'Login rejects an unknown user name'
    );

    AssertTest(
        $countSessions() === 1,
        'Unknown user name does not create another session'
    );


    // 7. User Name lookup should be case-insensitive
    $caseInsensitiveToken = $login->Login(
        strtoupper($username),
        $password
    );

    AssertTest(
        IsValidToken($caseInsensitiveToken),
        'Login accepts a user name with different capitalization'
    );

    echo "\nAll LoginService tests passed.\n";

} finally {
    RemoveTestUser($pdo, $userID);
}

?>