<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';
require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;
use UserBase\Tests\TestClock;

use UserBase\Repository\UserRepository;
use UserBase\Repository\PasswordResetRepository;
use UserBase\Repository\AuthSessionRepository;

use UserBase\Service\SessionService;
use UserBase\Service\PasswordResetService;

use UserBase\Config\SessionSettings;
use UserBase\Security\TokenUtility;



$now = new DateTimeImmutable(
    '2026-01-01 12:00:00',
    new DateTimeZone('UTC')
);

$clock = new TestClock($now);

$userRepo = new UserRepository($pdo);
$tokenRepo = new PasswordResetRepository($pdo);
$sessionRepo = new AuthSessionRepository($pdo);

$sessionSettings = new SessionSettings(
    'userbase_session',
    '/',
    'Lax',
    true,
    3600
);

$sessionService = new SessionService(
    $sessionRepo,
    $clock,
    $sessionSettings
);

$passwordResetService = new PasswordResetService(
    $pdo,
    $userRepo,
    $tokenRepo,
    $sessionService,
    $clock
);

try {

    // 1. Create a test user

    $userID = $userRepo->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    AssertTest($userID !== '', 'Create test user');

    // 2. Create a password reset token

    $rawToken = TokenUtility::NewToken();
    $tokenHash = TokenUtility::TokenToHash($rawToken);

    $expiresAt = $now->modify('+1 hour');

    $tokenRepo->Create(
        $userID,
        $tokenHash,
        $expiresAt,
        $now
    );

    // 3. Create an authenticated session

    $sessionToken = TokenUtility::NewToken();
    $sessionHash = TokenUtility::TokenToHash($sessionToken);

    $sessionRepo->Create(
        $userID,
        $sessionHash,
        $expiresAt
    );

    // 4. Verify our test data exists

    AssertTest(
        $tokenRepo->FindValidByTokenHash($tokenHash, $now) !== null,
        'Password reset token exists'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($sessionHash, $now) !== null,
        'Authenticated session exists'
    );

    // 5. Attempt password reset with an invalid token

    $invalidToken = TokenUtility::NewToken();
    $exceptionThrown = false;

    try {
        $passwordResetService->ResetPassword(
            $invalidToken,
            'AValidNewPassword123!'
        );
    } catch (DomainException) {
        $exceptionThrown = true;
    }

    AssertTest(
        $exceptionThrown,
        'Reject invalid password reset token'
    );

    AssertTest(
        $tokenRepo->FindValidByTokenHash($tokenHash, $now) !== null,
        'Invalid token attempt does not consume valid reset token'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($sessionHash, $now) !== null,
        'Invalid token attempt does not revoke authenticated session'
    );

    // 6. Attempt password reset with an expired token

    $expiredToken = TokenUtility::NewToken();
    $expiredTokenHash = TokenUtility::TokenToHash($expiredToken);

    $tokenRepo->Create(
        $userID,
        $expiredTokenHash,
        $now->modify('-1 hour'),
        $now->modify('-2 hours')
    );

    $exceptionThrown = false;

    try {
        $passwordResetService->ResetPassword(
            $expiredToken,
            'AValidNewPassword123!'
        );
    } catch (DomainException) {
        $exceptionThrown = true;
    }

    AssertTest(
        $exceptionThrown,
        'Reject expired password reset token'
    );

    AssertTest(
        $tokenRepo->FindValidByTokenHash($expiredTokenHash, $now) === null,
        'Expired password reset token is not considered valid'
    );

    AssertTest(
        $tokenRepo->FindValidByTokenHash($tokenHash, $now) !== null,
        'Expired token attempt does not consume valid reset token'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($sessionHash, $now) !== null,
        'Expired token attempt does not revoke authenticated session'
    );


    // 7. Attempt password reset with an already-consumed token

    $consumedToken = TokenUtility::NewToken();
    $consumedTokenHash = TokenUtility::TokenToHash($consumedToken);

    $tokenRepo->Create(
        $userID,
        $consumedTokenHash,
        $expiresAt,
        $now
    );

    $tokenRepo->Consume($consumedTokenHash, $now);

    $exceptionThrown = false;

    try {
        $passwordResetService->ResetPassword(
            $consumedToken,
            'AValidNewPassword123!'
        );
    } catch (DomainException) {
        $exceptionThrown = true;
    }

    AssertTest(
        $exceptionThrown,
        'Reject already-consumed password reset token'
    );

    AssertTest(
        $tokenRepo->FindValidByTokenHash($consumedTokenHash, $now) === null,
        'Consumed password reset token remains invalid'
    );

    AssertTest(
        $tokenRepo->FindValidByTokenHash($tokenHash, $now) !== null,
        'Consumed token attempt does not consume valid reset token'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($sessionHash, $now) !== null,
        'Consumed token attempt does not revoke authenticated session'
    );

    // 8. Attempt password reset with an invalid password

    $exceptionThrown = false;

    try {
        $passwordResetService->ResetPassword(
            $rawToken,
            'short'
        );
    } catch (DomainException) {
        $exceptionThrown = true;
    }

    AssertTest(
        $exceptionThrown,
        'Reject invalid new password'
    );

    AssertTest(
        $tokenRepo->FindValidByTokenHash($tokenHash, $now) !== null,
        'Invalid password attempt does not consume reset token'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($sessionHash, $now) !== null,
        'Invalid password attempt does not revoke authenticated session'
    );

    HandleComplete(__FILE__);

} finally {
    if ($userID !== null) {
        RemoveTestUser($pdo, $userID);
    }
}