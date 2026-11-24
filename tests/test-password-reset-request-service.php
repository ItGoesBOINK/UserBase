<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';
require_once __DIR__ . '/TestEmailSender.php';
require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;
use UserBase\Tests\TestClock;
use UserBase\Tests\TestEmailSender;
use UserBase\Time\TimeUtility;

use UserBase\Repository\UserRepository;
use UserBase\Repository\PasswordResetRepository;
use UserBase\Service\PasswordResetRequestService;
use UserBase\Security\TokenUtility;
use UserBase\Validation\InputValidator;

$testTime = new DateTimeImmutable(
    '2026-01-01 12:00:00',
    new DateTimeZone('UTC')
);

$clock = new TestClock($testTime);
$users = new UserRepository($pdo);
$tokens = new PasswordResetRepository($pdo);
$emailSender = new TestEmailSender();

$service = new PasswordResetRequestService(
    $config, $pdo, $users,
    $tokens, $clock, $emailSender
);

$unverifiedUserID = null;

try {

    // Create a verified test account
    $userID = $users->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    $users->MarkEmailVerified($userID, $testTime);

    // 1. Request a password reset
    $service->RequestReset($email);

    // 2. Confirm that a password-reset email was recorded
    AssertTest(
        count($emailSender->sentEmails) === 1,
        'Password Reset Email is Sent'
    );

    $sentEmail = $emailSender->sentEmails[0];

    AssertTest(
        $sentEmail['type'] === 'password_reset',
        'Correct E-Mail type is sent'
    );

    AssertTest(
        $sentEmail['email'] === $email,
        'Reset E-Mail uses the account E-Mail address'
    );

    AssertTest(
        $sentEmail['username'] === $username,
        'Reset E-Mail uses the Account User Name'
    );

    // 3. Verify the tokens
    $query = parse_url($sentEmail['url'], PHP_URL_QUERY);
    parse_str($query ?? '', $params);

    $rawToken = $params['token'] ?? null;

    AssertTest(
        is_string($rawToken) && InputValidator::IsValidVerificationToken($rawToken),
        'Reset E-Mail contains a valid token'
    );

    $tokenHash = TokenUtility::TokenToHash($rawToken);
    $storedToken = $tokens->FindValidByTokenHash($tokenHash, $testTime);

    AssertTest(
        $storedToken !== null,
        'Reset Token is stored and valid'
    );

    AssertTest(
        (int)$storedToken['user_id'] === (int)$userID,
        'Reset Token belongs to the correct user'
    );

    // 4. Request a reset for an unknown account
    $sentEmailCount = count($emailSender->sentEmails);
    $service->RequestReset('unknown-' . $suffix . '@test.test');

    AssertTest(
        count($emailSender->sentEmails) === $sentEmailCount,
        'Unknown account does not receive an email'
    );

    // 5. Unverified Account
    $unverifiedUsername = 'unverified_' . $suffix;
    $unverifiedEmail = $unverifiedUsername . '@test.test';

    $unverifiedUserID = $users->Create(
        $unverifiedUsername,
        InputValidator::NormalizeUserName($unverifiedUsername),
        $unverifiedEmail,
        InputValidator::NormalizeEmail($unverifiedEmail),
        $passwordHash
    );

    $sentEmailCount = count($emailSender->sentEmails);
    $service->RequestReset($unverifiedEmail);

    AssertTest(
        count($emailSender->sentEmails) === $sentEmailCount,
        'Unverified Account does not receive an email'
    );

    $sql = $pdo->prepare(
        'SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = :user_id'
    );

    $sql->execute(['user_id' => $unverifiedUserID]);

    AssertTest(
        (int) $sql->fetchColumn() === 0,
        'Unverified account does not receive a reset token'
    );

    AssertDomainException(
        fn () => $service->RequestReset('not-an-email'),
        'Invalid E-Mail Address is rejected'
    );

    AssertTest(
        count($emailSender->sentEmails) === 1,
        'Invalid E-Mail request sends no E-Mail'
    );


    // 6. Token Replacement

    $originalTokenHash = TokenUtility::TokenToHash($rawToken);
    $service->RequestReset($email);

    AssertTest(
        count($emailSender->sentEmails) === 2,
        'A New Reset Email is Sent'
    );

    $newEmail = $emailSender->sentEmails[1];
    $query = parse_url($newEmail['url'], PHP_URL_QUERY);
    $params = [];
    parse_str($query ?? '', $params);
    $newRawToken = $params['token'] ?? null;

    AssertTest(
        is_string($newRawToken) &&
        InputValidator::IsValidVerificationToken($newRawToken),
        'Replacement E-Mail contains a valid token'
    );

    $originalToken = $tokens->FindValidByTokenHash(
        $originalTokenHash,
        $testTime
    );

    $newToken = $tokens->FindValidByTokenHash(
        TokenUtility::TokenToHash($newRawToken),
        $testTime
    );

    AssertTest(
        $originalToken === null,
        'Previous Reset Token is invalidated'
    );

    AssertTest(
        $newToken !== null,
        'New Reset Token is valid'
    );

    // 7. E-Mail delivery failure
    $emailSender->shouldFail = true;
    $sentEmailCount = $emailSender->SentEmailCount();
    $service->RequestReset($email);

    AssertTest(
        $emailSender->SentEmailCount() === $sentEmailCount,
        'Failed E-Mail delivery does not record an email'
    );

    $sql = $pdo->prepare(
        'SELECT COUNT(*)
         FROM password_reset_tokens
         WHERE user_id = :user_id
            AND consumed_at IS NULL
            AND expires_at > :now'
    );

    $sql->execute([
        'user_id' => $userID,
        'now' => TimeUtility::FormatTimestamp($testTime)
    ]);

    AssertTest(
        (int)$sql->fetchColumn() === 1,
        'Reset Token is stored despite email delivery failure'
    );


    HandleComplete(__FILE__);

} finally {
    RemoveTestUser($pdo, $unverifiedUserID);
    RemoveTestUser($pdo, $userID);
}