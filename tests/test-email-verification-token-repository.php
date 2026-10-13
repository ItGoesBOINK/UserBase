<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use DateTimeImmutable;
use DateTimeZone;
use UserBase\Repository\EmailVerificationTokenRepository;

$users = new UserRepository($pdo);
$tokens = new EmailVerificationTokenRepository($pdo);
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$expiresAt = $now->modify('+24 hours');

try {

    // 1. Create a temp user to own the tokens
    $userID = $users->Create(
        $username,
        strtolower($username),
        $email,
        strtolower($email),
        password_hash($password, PASSWORD_DEFAULT)
    );

    AssertTest($userID !== '', 'Temporary User Created');


    // 2. Creae a verification token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    $tokenID = $tokens->Create(
        $userID,
        $tokenHash,
        $expiresAt
    );

    AssertTest($tokenID !== null, 'Token Creation returns an ID');


    // 3. Retrieve the token by its hash
    $token = $tokens->FindByTokenHash($tokenHash);

    AssertTest($token !== null, 'Token can be found by hash');
    AssertTest(
        (string) $token['user_id'] === $userID,
        'Token belongs to the expected user');
    AssertTest(
        (string) $token['id'] === $tokenID,
        'Retrieved Token has the expected ID');
    AssertTest(
        $token['used_at'] === null && $token['invalidated_at'] === null,
        'New Token is unused and valid (not invalidated)');
    AssertTest(
        $token['token_hash'] === hex2bin($tokenHash),
        'Stored binary hash matches the expected hash');


    // 4. An unknown hash should return null
    $missingHash = hash('sha256', random_bytes(32));

    AssertTest(
        $tokens->FindByTokenHash($missingHash) === null,
        'Unknown token hash returns null');


    // 5. Reject malformed hash input for Find
    try {
        $tokens->FindByTokenHash('Not-A-Valid-Hash');

        throw new RuntimeException('FAIL: Malformed token hash should be rejected on Find');
    } catch (InvalidArgumentException $e) {
        echo "PASS: Malformed token hash is rejected\n";
    }

    // 5.1 Reject invalid hashes for Create
    try {
        $tokens->Create($userID, 'not-a-valid-hash', $expiresAt);

        throw new RuntimeException('FAIL: Malformed token hash should be rejected during creation');
    } catch (InvalidArgumentException $e) {
        echo "PASS: Malformed token hash is rejected\n";
    }


    // 6. Mark the first token as used
    $usedAt = $now->modify('+1 minute');
    $tokens->MarkUsed($tokenID, $usedAt);

    AssertTest(
            $tokens->MarkUsed($tokenID, $usedAt),
            'Unused token can be consumed successfully');

    $usedToken = $tokens->FindByTokenHash($tokenHash);

    AssertTest(
        $usedToken !== null && $usedToken['used_at'] !== null,
        'Token can be marked as used');
    AssertTest(
        $usedToken['invalidated_at'] === null,
        'Using a token does not invalidate it');


    // 7. Create another token, then invalidate the outstanding tokens
    $secondRawToken = bin2hex(random_bytes(32));
    $secondHash = hash('sha256', $secondRawToken);
    $secondTokenID = $tokens->Create($userID, $secondHash, $expiresAt);
    $invalidatedAt = $now->modify('+2 minutes');
    $tokens->InvalidateOutstandingForUser($userID, $invalidatedAt);
    $secondToken = $tokens->FindByTokenHash($secondHash);

    AssertTest(
        $secondToken !== null && $secondToken['invalidated_at'] !== null,
        'Outstanding token is invalidated');
    AssertTest(
        $secondToken['used_at'] === null,
        'Invalidation does not mark a token as used');


    // 8. Previously used tokens remain used, not invalidated
    $usedTokenAfterInvalidation =
        $tokens->FindByTokenHash($tokenHash);

    AssertTest(
        $usedTokenAfterInvalidation !== null
            && $usedTokenAfterInvalidation['used_at'] !== null
            && $usedTokenAfterInvalidation['invalidated_at'] === null,
        'Invalidation preserves previously used tokens'
    );


    // 9. Attempting to mark an invalidated token as used must not
    // overwrite its invalidated state
    AssertTest(
        $tokens->MarkUsed($secondTokenID, $now->modify('+3 minutes')),
        'Unused token can be consumed successfully'
    );

    $secondTokenAfterMarkUsed = $tokens->FindByTokenHash($secondHash);

    AssertTest(
        $secondTokenAfterMarkUsed !== null
            && $secondTokenAfterMarkUsed['used_at'] === null
            && $secondTokenAfterMarkUsed['invalidated_at'] !== null,
        'Rejected consumption leaves the invalidated token unchanged'
    );


    echo "\nAll EmailVerificationTokenRepository tests passed.\n";

} finally {
    RemoveTestUser($pdo, $userID);
}

?>