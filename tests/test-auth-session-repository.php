<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';
require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;
use UserBase\Validation\InputValidator;
use UserBase\Security\TokenUtility;
use UserBase\Repository\UserRepository;
use UserBase\Repository\AuthSessionRepository;

$sessionRepo = new AuthSessionRepository($pdo);

$now = new DateTimeImmutable(
    '2026-01-01 12:00:00',
    new DateTimeZone('UTC')
);

$expiresAt = $now->modify('+1 day');
$revokedAt = $now->modify('+1 minute');

$userRepo = new UserRepository($pdo);

$otherUserID = null;

try {

    // 1. Create two concurrent users
    $userID = $userRepo->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    $otherUsername = 'test_other_' . $suffix;
    $otherUsernameNormalized = InputValidator::NormalizeUserName($otherUsername);
    $otherEmail = $otherUsername . '@example.test';
    $otherEmailNormalized = InputValidator::NormalizeEmail($otherEmail);

    $otherUserID = $userRepo->Create(
        $otherUsername,
        $otherUsernameNormalized,
        $otherEmail,
        $otherEmailNormalized,
        $passwordHash
    );

    AssertTest($userID !== '', 'Create primary test user');
    AssertTest($otherUserID !== '', 'Create secondary test user');

    // 2. Create test sessions, Verify they can be found
    $tokenA = TokenUtility::NewToken();
    $tokenB = TokenUtility::NewToken();
    $tokenC = TokenUtility::NewToken();
    $otherToken = TokenUtility::NewToken();

    $hashA = TokenUtility::TokenToHash($tokenA);
    $hashB = TokenUtility::TokenToHash($tokenB);
    $hashC = TokenUtility::TokenToHash($tokenC);
    $otherHash = TokenUtility::TokenToHash($otherToken);

    $sessionRepo->Create($userID, $hashA, $expiresAt);
    $sessionRepo->Create($userID, $hashB, $expiresAt);
    $sessionRepo->Create($userID, $hashC, $expiresAt);
    $sessionRepo->Create($otherUserID, $otherHash, $expiresAt);

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($hashA, $now) !== null,
        'Find active session by token hash'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($otherHash, $now) !== null,
        'Find active session belonging to another user'
    );

    // 3. Revoke an individual session

    AssertTest(
        $sessionRepo->Revoke($hashA, $revokedAt),
        'Revoke an active session'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($hashA, $now) === null,
        'Revoked session is no longer active'
    );

    AssertTest(
        !$sessionRepo->Revoke($hashA, $revokedAt),
        'Cannot revoke an already-revoked session'
    );

    // 4. Revoke all remaining sessions for the primary user

    $revokedCount = $sessionRepo->RevokeAllForUser(
        $userID,
        $revokedAt
    );

    AssertTest(
        $revokedCount === 2,
        'Bulk revocation affects all remaining sessions for the primary user'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($hashB, $now) === null,
        'Bulk revocation invalidates the second session'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($hashC, $now) === null,
        'Bulk revocation invalidates the third session'
    );

    AssertTest(
        $sessionRepo->FindActiveByTokenHash($otherHash, $now) !== null,
        'Bulk revocation does not affect another user’s session'
    );

    // 5. Repeating bulk revocation affects no additional sessions

    AssertTest(
        $sessionRepo->RevokeAllForUser($userID, $revokedAt) === 0,
        'Repeated bulk revocation returns zero'
    );

    HandleComplete(__FILE__);

} finally {
    if ($userID !== null)
    { RemoveTestUser($pdo, $userID); }
    if ($otherUserID !== null)
    { RemoveTestUser($pdo, $otherUserID); }
}