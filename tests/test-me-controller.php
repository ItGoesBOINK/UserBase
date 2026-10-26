<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';

use UserBase\Repository\UserRepository;
use UserBase\Repository\AuthSessionRepository;
use UserBase\Service\SessionService;
use UserBase\Tests\TestClock;
use UserBase\Time\SystemClock;
use UserBase\Security\TokenUtility;

/*
$clock = new TestClock(
    new DateTimeImmutable(
        '2026-01-15 12:00:00',
        new DateTimeZone('UTC')
    )
);
*/

$clock = new SystemClock();

$users = new UserRepository($pdo);
$authSessionRepo = new AuthSessionRepository($pdo);

$sessionSvc = new SessionService($authSessionRepo, $clock);

$userID = null;

try {

    $userID = $users->Create(
        $username,
        $usernameNormalized,
        $email,
        $emailNormalized,
        $passwordHash
    );

    $createSession = function () use ($sessionSvc, $userID): string {
        return $sessionSvc->Create($userID);
    };

    // Execute MeController in a separate process
    $requestMe = function (?string $token): array {

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'ignore_errors' => true,
                'header' => $token !== null
                    ? "Cookie: userbase_session={$token}\r\n"
                    : ''
            ]
        ]);

        $body = file_get_contents(
            'http://userbase/api/me',
            false,
            $context
        );

        $statusLine = $http_response_header[0] ?? '';

        preg_match('/\s(\d{3})\s/', $statusLine, $matches);

        $status = (int) ($matches[1] ?? 0);

        $bodyJson = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        return [
            'status' => $status,
            'body' => $bodyJson
        ];
    /*
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/me-controller-child.php'],
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ],
            $pipes
        );

        if (!is_resource($process)) {
            throw new RuntimeException(
                'Failed to start MeController test process'
            );
        }

        fwrite($pipes[0], json_encode([
            'token' => $token,
            'now' => $now->format('Y-m-d H:i:s.u')
        ], JSON_THROW_ON_ERROR));

        fclose($pipes[0]);

        $body = stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "MeController child process failed\n" .
                "Exit Code: [{$exitCode}]\n" .
                "STDOUT: [{$body}]\n" .
                "STDERR: [{$stderr}]\n"
            );
        }

        $bodyJson = json_decode($body, true, flags: JSON_THROW_ON_ERROR);

        return [
            'status' => (int) $matches[1],
            'body' => $bodyJson
        ];
    */
    };


    // 1. Missing Cookie
    $response = $requestMe(null);

    // var_dump($response);

    AssertTest(
        $response['status'] === 401 &&
        $response['body']['error']['code'] === 'unauthorized',
        '(Me) rejects a request without a session cookie'
    );


    // 2. Malformed Token
    $response = $requestMe('invalid-token');

    // var_dump($response);

    AssertTest(
        $response['status'] === 401,
        '(Me) rejects a malformed session token'
    );


    // 3. Valid, yet non-existent Token
    $response = $requestMe(str_repeat('a', 64));

    // var_dump($response);

    AssertTest(
        $response['status'] === 401,
        '(Me) rejects a nonexistent session token'
    );


    // 4. Revoked Session
    $revokedToken = $createSession();
    $tokenHash = TokenUtility::TokenToHash($revokedToken);
    $revoked = $authSessionRepo->Revoke(
        $tokenHash, $clock->Now()
    );

    // var_dump($revoked);

    $activeSession = $authSessionRepo->FindActiveByTokenHash
    ($tokenHash, $clock->Now());

    // var_dump($activeSession);

    $response = $requestMe($revokedToken);

    // var_dump($response);

    AssertTest(
        $response['status'] === 401,
        '(Me) rejects a revoked token'
    );


    // 5. Expired Session
    $expiredToken = $createSession();

    $sql = $pdo->prepare(
        'UPDATE auth_sessions
         SET expires_at = :expires_at
         WHERE token_hash = :token_hash'
    );

    $sql->execute([
        'expires_at' => (new DateTimeImmutable('-1 hour', new DateTimeZone('UTC')))
                        ->format('Y-m-d H:i:s.u'),
        'token_hash' => TokenUtility::HashToBinary(
            TokenUtility::TokenToHash($expiredToken))
    ]);

    $response = $requestMe($expiredToken);

    // var_dump($response);

    AssertTest(
        $response['status'] === 401,
        '(Me) rejects an expired token'
    );


    // 6. Valid Session
    $validToken = $createSession();
    $response = $requestMe($validToken);

    // var_dump($response);

    AssertTest(
        $response['status'] === 200,
        '(Me) accepts a valid session'
    );


    echo "\nAll MeController tests passed.\n";

} finally {
    if ($userID !== null) {
        $sql = $pdo->prepare(
            'DELETE FROM auth_sessions WHERE user_id = :user_id'
        );
        $sql->execute(['user_id' => $userID]);
    }

    RemoveTestUser($pdo, $userID);
}

?>