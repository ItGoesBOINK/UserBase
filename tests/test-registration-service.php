<?php

declare(strict_types=1);

namespace UserBase\Tests;

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';

use UserBase\Service\RegistrationService;
use UserBase\Service\EmailVerificationService;
use UserBase\Repository\UserRepository;
use UserBase\Repository\EmailVerificationTokenRepository;
use UserBase\Mail\PHPMailerEmailSender;
use UserBase\Tests\TestClock;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;

function AssertRegistrationRejected(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (DomainException $e) {
        echo "PASS: [{$message}]\n";
        return;
    }
}

$testTime = new DateTimeImmutable('2026-01-01 12:00:00', new DateTimeZone('UTC'));
$clock = new TestClock($testTime);
$users = new UserRepository($pdo);
$verificationRepo = new EmailVerificationTokenRepository($pdo);
$emailSender = new PHPMailerEmailSender($config);
$verificationService = new EmailVerificationService
    ($pdo, $users, $verificationRepo, $clock);
$registration = new RegistrationService
    ($users, $verificationService, $emailSender, $config);

try {

    // 1. Register a valid user
    $userID = $registration->Register($username, $email, $password);
    AssertTest($userID !== '', 'Registration Returns a User ID');


    // 2. Confirm the Account was stored correctly
    $user = $users->FindByID($userID);
    AssertTest($user !== null, 'Registered User Exists');
    AssertTest($user['username'] === $username, 'Original Username is Preserved');
    AssertTest($user['username_normalized'] === strtolower($username), 'User Name is Normalized');
    AssertTest($user['email'] === $email, 'Original E-Mail is Preserved');
    AssertTest($user['email_normalized'] === strtolower($email), 'E-Mail is Normalized');
    AssertTest(password_verify($password, $user['password_hash']), 'Password Hash Verifies Against the Original Password');
    AssertTest($user['password_hash'] !== $password, 'Original Password is NOT Stored');
    AssertTest($user['email_verified_at'] === null, 'New Account Remains Un-Verified');


    // 3. Reject an invalid user name
    AssertRegistrationRejected(
        fn () => $registration->Register(
            'ab',
            'invalid-name@example.test',
            $password
        ),
        'Invalid User-Name is Rejected'
    );


    // 4. Reject an Invalid E-Mail Address
    AssertRegistrationRejected(
        fn () => $registration->Register(
            'validname_' . $suffix,
            'not-an-email',
            $password
        ),
        'Invalid E-Mail is Rejected'
    );


    // 5. Reject a Short Password
    AssertRegistrationRejected(
        fn () => $registration->Register(
            'shortpass_' . $suffix,
            'shortpass_' . $suffix . '@example.test',
            'pass'
        ),
        'Short Password is Rejected'
    );


    // 6. Reject a Duplicate User-Name, regardless of case
    AssertRegistrationRejected(
        fn () => $registration->Register(
            strtoupper($username),
            'another_' . $suffix . '@example.test',
            $password
        ),
        'Duplicate User-Name is rejected case-insensitively'
    );


    // 7. Reject a Duplicate E-Mail, regardless of case
    AssertRegistrationRejected(
        fn () => $registration->Register(
            'another_' . $suffix,
            strtoupper($email),
            $password
        ),
        'Duplicate E-Mail is rejected case-insensitively'
    );


    // 8. Confirm Rejected Registrations Didn't Create Users
    AssertTest(
        $users->FindByUsernameNormalized('validname_' . $suffix) === null,
        'Invalid username registration created no account'
    );
    AssertTest(
        $users->FindByUsernameNormalized('shortpass_' . $suffix) === null,
        'Invalid password registration created no account'
    );
    AssertTest(
        $users->FindByUsernameNormalized('another_' . $suffix) === null,
        'Duplicate E-Mail registration created no account'
    );
    AssertTest(
        $users->FindByUsernameNormalized(strtoupper($username)) === null,
        'Duplicate User-Name created no account'
    );
    AssertTest(
        $users->FindByUsernameNormalized('ab') === null,
        'Short Name created no account'
    );

    echo "\nAll RegistrationService tests passed.\n";
} finally {
    RemoveTestUser($pdo, $userID);
}

?>