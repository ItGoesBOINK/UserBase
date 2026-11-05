<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use UserBase\Repository\AuthSessionRepository;
use UserBase\Repository\UserRepository;
use UserBase\Security\TokenUtility;
use UserBase\Service\SessionService;
use UserBase\Time\SystemClock;
use UserBase\Validation\InputValidator;


// Create the repositories and service used to set up the test data
$clock = new SystemClock();
$users = new UserRepository($pdo);
$authSessionRepo = new AuthSessionRepository($pdo);
$sessionSvc = new SessionService($authSessionRepo, $clock, $sessionSettings);

$userID = null;

/**
 * Send an HTTP request to the running application
 * @return array{status: int, body: array, headers: array}
 */
$request = function (
    string $method,
    string $path,
    ?string $cookieToken = null,
    ?string $bearerToken = null
): array {
    $headers = [];

    if ($cookieToken !== null) {
        $headers[] = "Cookie: userbase_session={$cookieToken}";
    }

    if ($bearerToken !== null) {
        $headers[] = "Authorization: Bearer {$bearerToken}";
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers)
        ]
    ]);

    $body = file_get_contents(
        'http://userbase/' . ltrim($path, '/'),
        false, $context
    );

    $responseHeaders = $http_response_header ?? [];

    if ($body === false) {
        throw new RuntimeException(
            'HTTP request failed: ' . $method . ' ' . $path
        );
    }

    preg_match(
        '/\b(\d{3})\b/',
        $responseHeaders[0] ?? '',
        $matches
    );

    $status = (int) ($matches[1] ?? 0);

    //var_dump($body);
    $bodyJson = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

    return [
        'status' => $status,
        'body' => $bodyJson,
        'headers' => $responseHeaders
    ];
};

try {

    // 0.  Create a unique test user
    $suffix = bin2hex(random_bytes(16));
    $username = 'logout_test_' . $suffix;
    $email = $username . '@test.test';

    $userID = $users->Create(
        $username,
        InputValidator::NormalizeUserName($username),
        $email,
        InputValidator::NormalizeEmail($email),
        password_hash('test-password-only', PASSWORD_DEFAULT)
    );

    // 1.  Log-out revokes an active session
    $token = $sessionSvc->Create($userID);
    $beforeLogout = $request('GET', '/api/me', $token);
    //var_dump($beforeLogout);

    AssertTest(
        $beforeLogout['status'] === 200,
        'Active session authenticates before log-out'
    );

    $logout = $request('POST', '/api/logout', $token);
    // var_dump($logout);

    AssertTest(
        $logout['status'] === 200,
        'Active session can log-out'
    );


    // 2.  The old token must no longer authenticate
    $afterLogout = $request('GET', '/api/me', $token);

    AssertTest(
        $afterLogout['status'] === 401,
        'Revoked token cannot authenticate'
    );


    // 3.  Log-out without a cookie still succeeds
    $missingCookie = $request('POST', '/api/logout');

    AssertTest(
        $missingCookie['status'] === 200,
        'Missing cookie is handled successfully'
    );


    // 4.  Log-out with a malformed token still succeeds
    $malformed = $request('POST', 'api/logout', 'invalid-token');

    AssertTest(
        $malformed['status'] === 200,
        'Malformed token is handled successfully'
    );


    // 5.  Repeating log-out with the old token is safe
    $repeatedLogout = $request('POST', '/api/logout', $token);

    AssertTest(
        $repeatedLogout['status'] === 200,
        'Repeated log-out succeeds'
    );


    // 6.  Response must expire the session cookie
    $setCookieHeaders = array_values(array_filter(
        $logout['headers'],
        static fn (string $header): bool =>
            stripos($header, 'Set-Cookie: userbase_session=') === 0
    ));

    AssertTest(
        count($setCookieHeaders) > 0,
        'Response includes the session cookie'
    );

    AssertTest(
        count(array_filter(
            $setCookieHeaders,
            static function (string $header): bool {
                $patt = '/(?:^|;\s*)expires=([^;]+)/i';
                if (!preg_match($patt, $header, $matches)) { return false; }

                $expiresAt = strtotime($matches[1]);

                return $expiresAt !== false &&
                       $expiresAt < time();
            }
        )) > 0,
        'Session cookie is expired'
    );

    $bearerToken = $sessionSvc->Create((string)$userID);
    $beforeBearerLogout = $request(
        'GET', '/api/me', null, $bearerToken
    );

    AssertTest(
        $beforeBearerLogout['status'] === 200,
        'Bearer session authenticates before Log Out'
    );

    $bearerLogout = $request(
        'POST', '/api/logout', null, $bearerToken
    );

    AssertTest(
        $bearerLogout['status'] === 200,
        'Bearer session can Log Out'
    );

    $afterBearerLogout = $request(
        'GET', '/api/me', null, $bearerToken
    );

    AssertTest(
        $afterBearerLogout['status'] === 401,
        'Revoked Bearer Token cannot authenticate'
    );

    AssertTest(
        !HasResponseHeader($bearerLogout['headers'], 'Set-Cookie'),
        'Bearer Log Out does not set or clear cookies'
    );

    /**
     * TODO: testing a request that supplies both credentials,
     * and verifying the deliberate HTTP 400 response.
     */


    HandleComplete(__FILE__);

} finally {
    if ($userID !== null) {
        // Remove sessions before removing their owning user
        $sql = $pdo->prepare(
            'DELETE FROM auth_sessions WHERE user_id = :user_id'
        );
        $sql->execute(['user_id' => $userID]);

        RemoveTestUser($pdo, $userID);
    }
}

?>