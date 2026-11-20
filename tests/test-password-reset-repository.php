<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use UserBase\Repository\PasswordResetRepository;
use UserBase\Repository\UserRepository;

$users = new UserRepository($pdo);
$resetTokens = new PasswordResetRepository($pdo);

$userID = '';
$otherUserID = '';

try {
    // 1. Create test users

    $userID = $users->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    $otherUsername = 'reset_other_' . $suffix;
    $otherEmail = 'reset_other_' . $suffix . '@example.test';

    $otherUserID = $users->Create(
        $otherUsername,
        strtolower($otherUsername),
        $otherEmail,
        strtolower($otherEmail),
        $passwordHash
    );

    AssertTest($userID !== '', 'Create primary test user');
    AssertTest($otherUserID !== '', 'Create secondary test user');


    // 2. Create a password-reset token

    $now = new DateTimeImmutable(
        '2026-01-15 12:00:00.000000',
        new DateTimeZone('UTC')
    );

    $validHash = hash('sha256', 'valid-reset-token', true);

    $tokenID = $resetTokens->Create(
        $userID,
        $validHash,
        $now->modify('+1 hour'),
        $now
    );

    AssertTest(
        $tokenID !== '',
        'Create Password Reset Token returns a Database ID'
    );


    // 3. Find a valid token

    $token = $resetTokens->FindValidByTokenHash(
        $validHash,
        $now
    );

    AssertTest($token !== null, 'Find Valid Password Reset Token');

    AssertTest(
        (string) $token['id'] === $tokenID,
        'Found Token ID Matches'
    );

    AssertTest(
        (string) $token['user_id'] === $userID,
        'Found Token User ID Matches'
    );


    // 4. Expired tokens should not be found

    $expiredHash = hash('sha256', 'expired-reset-token', true);

    $resetTokens->Create(
        $userID,
        $expiredHash,
        $now->modify('-1 second'),
        $now->modify('-2 hours')
    );

    AssertTest(
        $resetTokens->FindValidByTokenHash($expiredHash, $now) === null,
        'Expired Password Reset Token Returns NULL'
    );


    // 5. Consume a valid token

    AssertTest(
        $resetTokens->Consume($validHash, $now),
        'Consume Valid Password Reset Token'
    );

    AssertTest(
        $resetTokens->FindValidByTokenHash($validHash, $now) === null,
        'Consumed Token Cannot Be Found as Valid'
    );


    // 6. A token cannot be consumed twice

    AssertTest(
        !$resetTokens->Consume($validHash, $now),
        'Previously Consumed Token Cannot Be Consumed Again'
    );


    // 7. Expired tokens cannot be consumed

    AssertTest(
        !$resetTokens->Consume($expiredHash, $now),
        'Expired Password Reset Token Cannot Be Consumed'
    );


    // 8. Create outstanding tokens for invalidation tests

    $invalidateHash1 = hash('sha256', 'invalidate-token-1', true);
    $invalidateHash2 = hash('sha256', 'invalidate-token-2', true);
    $otherUserHash = hash('sha256', 'other-user-token', true);

    $resetTokens->Create(
        $userID,
        $invalidateHash1,
        $now->modify('+1 hour'),
        $now
    );

    $resetTokens->Create(
        $userID,
        $invalidateHash2,
        $now->modify('+1 hour'),
        $now
    );

    $resetTokens->Create(
        $otherUserID,
        $otherUserHash,
        $now->modify('+1 hour'),
        $now
    );


    // 9. Invalidate all outstanding tokens for one user

    $invalidatedCount = $resetTokens->InvalidateForUser(
        $userID,
        $now
    );

    AssertTest(
        $invalidatedCount === 3,
        'Invalidate All Outstanding Tokens for User'
    );

    AssertTest(
        $resetTokens->FindValidByTokenHash($invalidateHash1, $now) === null,
        'First Invalidated Token Is No Longer Valid'
    );

    AssertTest(
        $resetTokens->FindValidByTokenHash($invalidateHash2, $now) === null,
        'Second Invalidated Token Is No Longer Valid'
    );


    // 10. Other users' tokens remain unaffected

    AssertTest(
        $resetTokens->FindValidByTokenHash($otherUserHash, $now) !== null,
        'Invalidating One User Does Not Affect Another User'
    );


    HandleComplete(__FILE__);

} finally {
    if ($userID !== '') {
        RemoveTestUser($pdo, $userID);
    }

    if ($otherUserID !== '') {
        RemoveTestUser($pdo, $otherUserID);
    }
}

?>
