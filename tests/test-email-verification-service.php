<?php

declare(strict_types=1);

use UserBase\Database\DatabaseConnection;
use UserBase\Repository\EmailVerificationTokenRepository;
use UserBase\Repository\UserRepository;
use UserBase\Service\EmailVerificationService;
use UserBase\Tests\TestClock;

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';

$pdo->exec("SET time_zone = '+00:00'");

$users = new UserRepository($pdo);
$tokens = new EmailVerificationTokenRepository($pdo);
$testTime = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
$clock = new TestClock($testTime);
$service = new EmailVerificationService($pdo, $users, $tokens, $clock);
$userIDs = [];

function CreateTestUser(UserRepository $users, array &$userIDs): string {

    $suffix = bin2hex(random_bytes(16));
    $username = 'verify_test_' . $suffix;
    $email = $username . '@test.test';
    $password = 'test-password-only';

    $userID = $users->Create(
        $username,
        strtolower($username),
        $email,
        strtolower($email),
        password_hash($password, PASSWORD_DEFAULT)
    );

    $userIDs[] = $userID;

    return $userID;
}


try {

    // 1. Issue a token and verify its stored representation
    $utc = new DateTimeZone('UTC');
    $userID = CreateTestUser($users, $userIDs);
    $rawToken = $service->IssueToken($userID);

    AssertTest(
        IsValidToken($rawToken),
        'Issued Token is a 64-character hexadecimal string');

    $tokenHash = hash('sha256', $rawToken);
    $storedToken = $tokens->FindByTokenHash($tokenHash);

    AssertTest(
        $storedToken !== null,
        'Issued Token is can be found by its hash');

    AssertTest(
        $storedToken !== null && (string) $storedToken['user_id'] === $userID,
        'Issued Token belongs to the expected user');

    AssertTest(
        $storedToken !== null && $storedToken['token_hash'] === hex2bin($tokenHash),
        'Database stores the token hash, not the raw token');

    $expectedExpiry = $clock->Now()->modify('+24 hours');
    $actualExpiry = new DateTimeImmutable($storedToken['expires_at'], $utc);

    AssertTest(
        $actualExpiry == $expectedExpiry,
        'Token expires exactly 24 hour after issuance');

    // 2. Issuing a replacement invalidates the previous token
    $replacementRawToken = $service->IssueToken($userID);
    $replacementHash = hash('sha256', $replacementRawToken);

    $originalAfterReplacement = $tokens->FindByTokenHash($tokenHash);
    $replacement = $tokens->FindByTokenHash($replacementHash);

    AssertTest(
            $originalAfterReplacement !== null &&
            $originalAfterReplacement['invalidated_at'] !== null,
            'Issuing a replacement invalidates the previous token');

    AssertTest(
            $replacement !== null &&
            $replacement['invalidated_at'] === null &&
            $replacement['used_at'] === null,
            'Replacement token is active and unused');

    AssertDomainException(
        fn () => $service->VerifyToken($rawToken),
        'Invalid token is rejected');

    // 3. A valid token verifies the account
    $clock->Advance('+1 hour');
    $service->VerifyToken($replacementRawToken);
    $verifiedUser = $users->FindByID($userID);
    $usedReplacement = $tokens->FindByTokenHash($replacementHash);

    AssertTest(
        $verifiedUser !== null &&
        $verifiedUser['email_verified_at'] !== null,
        'Valid token marks the email address as verified');

    AssertTest(
        $usedReplacement !== null &&
        $usedReplacement['used_at'] !== null,
        'Successful verification marks the token as used');

    AssertTest(
        $usedReplacement !== null &&
        $usedReplacement['invalidated_at'] === null,
        'Successful used token is not marked invalidated');


    // 4. A used token cannot verify the account again
    AssertDomainException(
        fn () => $service->VerifyToken($replacementRawToken),
        'Previously used token is rejected'
    );

    // 5. An unknown or malformed token is rejected
    AssertDomainException(
        fn () => $service->VerifyToken(str_repeat('a', 64)),
        'Unknown token is rejected'
    );
    AssertDomainException(
        fn () => $service->VerifyToken('not-a-valid-token'),
        'Malformed token is rejected'
    );



    // 6. Expiration is tested by advancing the injected clock
    $expiredUserID = CreateTestUser($users, $userIDs);
    $expiredRawToken = $service->IssueToken($expiredUserID);
    $expiredHash = hash('sha256', $expiredRawToken);
    $clock->Advance('+25 hours');

    AssertDomainException(
        fn () => $service->VerifyToken($expiredRawToken),
        'Expired token is rejected');

    $expiredUser = $users->FindByID($expiredUserID);
    $expiredToken = $tokens->FindByTokenHash($expiredHash);

    AssertTest(
        $expiredUser !== null &&
        $expiredUser['email_verified_at'] === null,
        'Rejected expired token remains unused'
    );


    // 7.Already-verified accounts cannot receive new tokens
    AssertDomainException(
        fn () => $service->IssueToken($userID),
        'Issuing a token for a verified account is rejected');

    echo "\nAll EmailVerificationService tests passed.\n";

} finally {

    // Delete tokens belonging to the temporary test users
    foreach ($userIDs as $userID) {
        $sql = $pdo->prepare(
            'DELETE FROM email_verification_tokens
             WHERE user_id = :user_id'
        );
        $sql->execute(['user_id' => $userID]);
    }

    foreach ($userIDs as $userID) {
        RemoveTestUser($pdo, $userID);
    }

    echo "\nTemporary users and associated tokes were cleaned up.\n";
}


?>