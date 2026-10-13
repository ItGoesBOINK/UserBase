<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use DomainException;
use RuntimeException;
use UserBase\Database\DatabaseConnection;
use UserBase\Repository\UserRepository;

function AssertTest(bool $condition, string $message): void
{
    if (!$condition)
    {
        throw new RuntimeException("FAIL: [{$message}]\n");
    }

    echo "PASS: [{$message}]\n";
}

function AssertDomainException(
    callable $operation,
    string $message
): void {
    try {
        $operation();
    } catch (DomainException $e) {
        echo "PASS: {$message}\n";
        return;
    }

    throw new RuntimeException("FAIL: {$message}\n");
}

function RemoveTestUser($obj, $id)
{
    // Remove only the temporary user created by this test
    if ($id !== null)
    {
        $sql = $obj->prepare('DELETE FROM users WHERE id = :id');
        $sql->execute(['id' => $id]);

        echo "Test User Cleaned Up.\n";
    }
}

function IsValidToken($t): bool
{ return preg_match('/\A[a-f0-9]{64}\z/', $t) === 1; }

$database = new DatabaseConnection($config);
$pdo = $database->GetConnection();

$userID = null;

$suffix = bin2hex(random_bytes(8));
$username = 'test_' . $suffix;
$email = $username . '@example.test';
$password = 'test-password-only';

$usernameNormalized = strtolower($username);
$emailNormalized = strtolower($email);
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

?>