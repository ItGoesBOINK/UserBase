<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once __DIR__ . '/Out.php';

use UserBase\Database\DatabaseConnection;
use UserBase\Repository\UserRepository;
use UserBase\Validation\InputValidator;
use UserBase\Time\TimeUtility;
use UserBase\Tests\Out;


function AssertTest(bool $condition, string $message): void
{
    if (!$condition)
    {
        //throw new RuntimeException("FAIL: [{$message}]\n");
        throw new RuntimeException(Out::GetFail($message));
    }

    //echo "PASS: [{$message}]\n";
    echo Out::Pass($message);
}

function AssertDomainException(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (DomainException $e) {
        // echo "PASS: {$message}\n";
        Out::Pass($message);
        return;
    }

    throw new RuntimeException(Out::GetFail($message));
}

function RemoveTestUser($obj, $id)
{
    // Remove only the temporary user created by this test
    if ($id !== null)
    {
        $sql = $obj->prepare('DELETE FROM users WHERE id = :id');
        $sql->execute(['id' => $id]);

        //echo "Test User Cleaned Up.\n";
        Out::Info("Test User Cleaned Up.");
    }
}


function IsValidToken($t): bool
{ return InputValidator::IsValidVerificationToken($t); }

function ExtractFileName(string $path): string {
    $a = strrpos($path, '/test-') + 6;
    $l = strrpos($path, '.') - $a;
    return substr($path, $a, $l);
}

function HandleComplete($name): void {
    $msg = "\nAll [" . ExtractFileName($name) . "] tests passed.\n";
    Out::ColorLine(Out::GREEN, $msg);
}

function HasResponseHeader(
    array $headers,
    string $headerName
): bool {
    return count(array_filter(
        $headers,
        static fn (string $header): bool =>
            stripos($header, $headerName) === 0
    )) > 0;
}

function HasCookieHeader(array $headers): bool {
    return HasResponseHeader($headers, 'Set-Cookie: userbase_session=');
}


$database = new DatabaseConnection($config);
$pdo = $database->GetConnection();

$userID = null;

$suffix = bin2hex(random_bytes(8));
$username = 'test_' . $suffix;
$email = $username . '@example.test';
$password = 'test-password-only';

$usernameNormalized = InputValidator::NormalizeUserName($username);
$emailNormalized = InputValidator::NormalizeEmail($email);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

?>