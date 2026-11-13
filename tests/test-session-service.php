<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';

use UserBase\Repository\AuthSessionRepository;
use UserBase\Repository\UserRepository;
use UserBase\Service\SessionService;
use UserBase\Tests\TestClock;
use UserBase\Security\TokenUtility;

$users = new UserRepository($pdo);
$sessions = new AuthSessionRepository($pdo);

try {
    // 1. Create a test user
    $userID = $users->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    $clock = new TestClock(
        new DateTimeImmutable(
            '2026-01-15 12:00:00',
            new DateTimeZone('UTC')
        )
    );

    $service = new SessionService($sessions, $clock, $sessionSettings);

    // 2. Create a session
    $grant = $service->Create($userID);
    $sessionToken = $grant->sessionToken;
    //$syncToken = $grant->syncToken;

    AssertTest(
        IsValidToken($sessionToken),
        'Create returns a valid 64-character Session Token'
    );

    /*
    AssertTest(
        IsValidToken($syncToken),
        'Create returns a valid 64-character CSRF Synchronizer Token'
    );

    AssertTest(
        $sessionToken !== $syncToken,
        'Session and Sync Tokens are different'
    );
    */

    // 3. Confirm the database stores only the token hash
    $sessionTokenHash = hash('sha256', $sessionToken);

    $sql = $pdo->prepare(
        'SELECT session_token_hash /*, sync_token_hash */
         FROM auth_sessions
         WHERE user_id = :user_id'
    );

    $sql->execute(['user_id' => $userID]);

    $storedSession = $sql->fetch(PDO::FETCH_ASSOC);
    $storedSessionTokenHash = $storedSession['session_token_hash'];
    //$storedSyncTokenHash = $storedSession['sync_token_hash'];

    AssertTest(
        $storedSessionTokenHash === TokenUtility::TokenToBinaryHash($sessionToken),
        'Database stores the SHA-256 session token hash'
    );

    /*
    AssertTest(
        $storedSyncTokenHash === TokenUtility::TokenToBinaryHash($syncToken),
        'Database stores the SHA-256 CSRF sync token hash'
    );
    */

    AssertTest(
        $storedSessionTokenHash !== $sessionToken,
        'Database does not store the raw session token'
    );

    /*
    AssertTest(
        $storedSyncTokenHash !== $syncToken,
        'Database does not store the raw CSRF sync token'
    );
    */


    // 4. Validate the new session
    $session = $service->Validate($sessionToken);

    AssertTest(
        $session !== null &&
        (string) $session['user_id'] === $userID,
        'New session validates and returns the correct user ID'
    );

    // 5. Reject malformed and unknown tokens
    AssertTest(
        $service->Validate('invalid-token') === null,
        'Malformed token (invalid length) is rejected'
    );

    AssertTest(
        $service->Validate(str_repeat('a', 64)) === null,
        'Malformed token (invalid hash value) is rejected'
    );

    /*
    // 6. Rotate the CSRF synchronizer token
    $rotatedSyncToken = $service->RotateSyncToken($sessionToken);

    AssertTest(
        $rotatedSyncToken !== null &&
        IsValidToken($rotatedSyncToken),
        'Active session successfully rotates its CSRF synchronizer token'
    );

    AssertTest(
        $rotatedSyncToken !== $syncToken,
        'Rotation generates a different CSRF synchronizer token'
    );
    */

    /*
    // 6.5. confirm the database contains the new hash
    $sql = $pdo->prepare(
        'SELECT sync_token_hash
         FROM auth_sessions
         WHERE session_token_hash = :session_token_hash'
    );

    $sql->execute([
        'session_token_hash' => TokenUtility::TokenToBinaryHash($sessionToken)
    ]);

    $storedSyncHash = $sql->fetchColumn();

    AssertTest(
        $storedSyncHash === TokenUtility::TokenToBinaryHash($rotatedSyncToken),
        'Database stores the newly rotated CSRF synchronizer token hash'
    );

    AssertTest(
        $storedSyncHash !== TokenUtility::TokenToBinaryHash($syncToken),
        'Previous CSRF Sync-Token hash is no longer stored'
    );


    AssertTest(
        $service->ValidateSyncToken($sessionToken, $rotatedSyncToken),
        'New CSRF Sync Token validates against its session'
    );

    AssertTest(
        !$service->ValidateSyncToken($sessionToken, $syncToken),
        'Previous CSRF Sync-Token is rejected after rotation'
    );

    AssertTest(
        !$service->ValidateSyncToken($sessionToken, 'invalid-token'),
        'Malformed Token is rejected by Sync Token Validation'
    );
    */

    // 7. Revoke the session
    $service->Revoke($sessionToken);

    AssertTest(
        $service->Validate($sessionToken) === null,
        'Revoked session is rejected'
    );

    /*
    AssertTest(
        $service->RotateSyncToken($sessionToken) === null,
        'Revoked Session cannot rotate its CSRF synchronizer token'
    );


    AssertTest(
        !$service->ValidateSyncToken($sessionToken, $rotatedSyncToken),
        'Revoked session cannot validate its sync token'
    );
    */

    // 8. Create another session and advance beyond expiration
    $grant = $service->Create($userID);
    $expiring_sessionToken = $grant->sessionToken;
    //$expiring_syncToken = $grant->syncToken;

    AssertTest(
        $service->Validate($expiring_sessionToken) !== null,
        'Second session is initially valid'
    );

    $clock->Advance('+25 hours');

    AssertTest(
        $service->Validate($expiring_sessionToken) === null,
        'Expired session is rejected'
    );

    /*
    AssertTest(
        !$service->ValidateSyncToken($expiring_sessionToken, $expiring_syncToken),
        'Expired session cannot validate from its own CSRF Sync Token'
    );


    AssertTest(
        $service->RotateSyncToken($expiring_sessionToken) === null,
        'Expired Session cannot rotate its CSRF synchronizer token'
    );
    */


    HandleComplete(__FILE__);

} finally {
    RemoveTestUser($pdo, $userID);
}

?>