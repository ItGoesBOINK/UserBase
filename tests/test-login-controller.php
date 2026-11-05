<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/Out.php';

use UserBase\Tests\Out;
use UserBase\Validation\InputValidator;
use UserBase\Repository\UserRepository;
use UserBase\Time\SystemClock;

/**
 * Send an HTTP request to the running application.
 *
 * @return array{status: int, body: array, headers: array}
 */
$Request = function (
    string $method,
    string $path,
    ?string $jsonBody = null,
    array $headers = []
): array {

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers),
            'content' => $jsonBody ?? ''
        ]
    ]);

    $body = file_get_contents(
        'http://userbase/' . ltrim($path, '/'),
        false,
        $context
    );

    $responseHeaders = $http_response_header ?? [];

    if ($body === false) {
        $msg = 'HTTP Request Failed: ' . $method . ' ' . $path;
        throw new RuntimeException($msg);
    }

    preg_match(
        '/\b(\d{3})\b/',
        $responseHeaders[0] ?? '',
        $matches
    );

    $status = (int) ($matches[1] ?? 0);

    $bodyJson = json_decode(
        $body,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    return [
        'status' => $status,
        'body' => $bodyJson,
        'headers' => $responseHeaders
    ];
};

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

$users = new UserRepository($pdo);
$clock = new SystemClock();
$jsonHeaders = [
   'Content-Type: application/json',
   'Accept: application/json'
];

try {

    // 0    Create a verified test user
    $userID = $users->Create(
        $username, $usernameNormalized,
        $email, $emailNormalized,
        $passwordHash
    );

    $users->MarkEmailVerified($userID, $clock->Now());

    // 1	Login without session_transport
    //      expected: 200, cookie set
    $json = json_encode([
        'username' => $username,
        'password' => $password
    ], JSON_THROW_ON_ERROR);

    $defaultLogin = $Request(
        'POST', '/api/login',
        $json, $jsonHeaders
    );

    AssertTest(
        $defaultLogin['status'] === 200,
        'Login defaults to cookie transport'
    );

    //var_dump($defaultLogin);

    AssertTest(
        isset($defaultLogin['body']['data']['message']) &&
        $defaultLogin['body']['data']['message'] === 'Login Successful',
        'Default Cookie Login returns success message'
    );

    AssertTest(
        !isset($defaultLogin['body']['session_token']),
        'Default Cookie Login does not expose session token'
    );

    AssertTest(
        HasCookieHeader($defaultLogin['headers']),
        'Default Cookie Login sets session cookie'
    );


    // 2	Login with explicit cookie
    //      expected: 200, cookie set
    $json = json_encode([
        'username' => $username,
        'password' => $password,
        'session_transport' => 'cookie'
    ], JSON_THROW_ON_ERROR);

    $cookieLogin = $Request(
        'POST', '/api/login',
        $json, $jsonHeaders
    );

    AssertTest(
        $cookieLogin['status'] === 200,
        'Explicit Cookie Login succeeds'
    );

    AssertTest(
        !isset($cookieLogin['body']['session_token']),
        'Explicit Cookie Login does not expose session token'
    );

    AssertTest(
        HasCookieHeader($cookieLogin['headers']),
        'Explicit Cookie Login sets session cookie'
    );

    // 3	Login with explicit bearer transport
    //      expected: 200, token in JSON
    $json = json_encode([
        'username' => $username,
        'password' => $password,
        'session_transport' => 'bearer'
    ], JSON_THROW_ON_ERROR);

    $bearerLogin = $Request(
        'POST', '/api/login',
        $json, $jsonHeaders
    );

    AssertTest(
        $bearerLogin['status'] === 200,
        'Bearer Login succeeds'
    );

    AssertTest(
        isset($bearerLogin['body']['data']['session_token']) &&
        is_string($bearerLogin['body']['data']['session_token']),
        'Bearer Login returns a session token'
    );

    AssertTest(
        ($bearerLogin['body']['data']['token_type'] ?? null) === 'Bearer',
        'Bearer Login identifies a token type'
    );

    AssertTest(
        ($bearerLogin['body']['data']['expires_in'] ?? null) ===
            $sessionSettings->lifetimeSeconds,
        'Bearer Login returns the configured session life-time'
    );

    AssertTest(
        !HasCookieHeader($bearerLogin['headers']),
        'Bearer Login does not set a session cookie'
    );

    $bearerToken = $bearerLogin['body']['data']['session_token'];
    $authenticated = $Request(
        'GET', '/api/me', null,
        [
            'Authorization: Bearer ' . $bearerToken,
            'Accept: application/json'
        ]
    );

    AssertTest(
        $authenticated['status'] === 200,
        'Bearer Token authenticates successfully'
    );

    // 4	Invalid transport
    //      expected: 400
    $json = json_encode([
        'username' => $username,
        'password' => $password,
        'session_transport' => 'something_else'
    ], JSON_THROW_ON_ERROR);

    $response = $Request(
        'POST', '/api/login',
        $json, $jsonHeaders
    );

    AssertTest(
        $response['status'] === 400,
        'Invalid Session Transport returns HTTP 400'
    );

    // 5	Missing username
    //      expected: 400
    $json = json_encode([
        'password' => $password
    ], JSON_THROW_ON_ERROR);

    $response = $Request(
        'POST', '/api/login',
        $json, $jsonHeaders
    );

    AssertTest(
        $response['status'] === 400,
        'Missing User-Name returns HTTP 400'
    );

    // 6	Missing password
    //      expected: 400
    $json = json_encode([
        'username' => $username
    ], JSON_THROW_ON_ERROR);

    $response = $Request(
        'POST', '/api/login',
        $json, $jsonHeaders
    );

    AssertTest(
        $response['status'] === 400,
        'Missing Password returns HTTP 400'
    );

    // 7	Invalid JSON
    //      expected: 400
    $response = $Request(
        'POST', '/api/login',
        '{invalid_jason', $jsonHeaders
    );

    AssertTest(
        $response['status'] === 400,
        'Malformed JSON returns HTTP 400'
    );

    // 8	Incorrect password
    //      expected: Authentication failure
    $json = json_encode([
        'username' => $username,
        'password' => 'definitely-the-wrong-password',
    ], JSON_THROW_ON_ERROR);

    $response = $Request(
        'POST', '/api/login',
        $json, $jsonHeaders
    );

    AssertTest(
        $response['status'] === 400,
        'Invalid Login credentials returns HTTP 400'
    );


    // 9	Unverified email
    //      expected: Authentication failure
    $unverifiedUserID = null;
    $unverifiedUsername = $username . '_unverified';
    $unverifiedEmail = $unverifiedUsername . '@test.test';



} finally {

    if ($userID !== null)
    {
        // Remove Sessions before removing their owning user
        $sql = $pdo->prepare(
            'DELETE FROM auth_sessions WHERE user_id = :user_id'
        );
        $sql->execute(['user_id' => $userID]);

        RemoveTestUser($pdo, $userID);
    }

}

?>