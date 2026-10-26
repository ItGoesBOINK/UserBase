<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use UserBase\Repository\UserRepository;

$users = new UserRepository($pdo);

try {
    // 1. Create a User
    $userID = $users->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    AssertTest($userID !== '', 'Create User returns a Database ID');


    // 2. Find User by ID
    $user = $users->FindByID($userID);

    AssertTest($user !== null, "Find User by ID");
    AssertTest($user['username'] === $username, "Stored Username Matches");
    AssertTest($user['email'] === $email, "Stored E-Mail Matches");
    AssertTest(password_verify('test-password-only', $user['password_hash']), "Stored password hash verifies correctly");
    AssertTest($user['email_verified_at'] === null, "New User E-Mail is initially un-verified");


    // 3. Find the User by Normalized Username
    $foundByUsername = $users->FindByUsernameNormalized($usernameNormalized);

    AssertTest(
        $foundByUsername !== null && (string) $foundByUsername['id'] === $userID,
        "Find User by Normalized Username");


    // 4. Find the User by Normalized E-Mail
    $foundByEmail = $users->FindByEmailNormalized($emailNormalized);

    AssertTest(
            $foundByEmail !== null && (string) $foundByEmail['id'] === $userID,
            "Find User by Normalized E-Mail");


    // 5. Confirm Missing Users return NULL
    AssertTest(
        $users->FindByID('999999999999999999') === null,
        'Missing User ID returns NULL');

    AssertTest(
            $users->FindByUsernameNormalized('missing_' . $suffix) === null,
            'Missing User Name returns NULL');

    AssertTest(
            $users->FindByEmailNormalized('missing_' . $suffix . '@example.test') === null,
            'Missing E-Mail returns NULL');


    // 6. Mark E-Mail as Verified
    $verifiedAt = new DateTimeImmutable('2026-01-15 12:34:56.123456', new DateTimeZone('UTC'));

    $users->MarkEmailVerified($userID, $verifiedAt);

    $verifiedUser = $users->FindByID($userID);

    AssertTest(
        $verifiedUser !== null && $verifiedUser['email_verified_at'] === '2026-01-15 12:34:56.123456',
        'E-Mail Verification Time-Stamp is saved'
    );

    echo "\nAll UserRepository tests passed.\n";

} finally {
    RemoveTestUser($pdo, $userID);
}

?>