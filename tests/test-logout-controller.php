<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use UserBase\Repository\AuthSessionRepository;
use UserBase\Repository\UserRepository;
use UserBase\Security\TokenUtility;
use UserBase\Service\SessionService;
use UserBase\Time\SystemClock;
use UserBase\Validation\InputValidator;

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

    $userID = null;

    $m_get = 'GET';
    $m_post = 'POST';
    $p_id = '/api/id';
    $p_logout = '/api/logout';

    // Create the repositories and service used to set up the test data
    $clock = new SystemClock();
    $users = new UserRepository($pdo);
    $authSessionRepo = new AuthSessionRepository($pdo);
    $sessionSvc = new SessionService($authSessionRepo, $clock, $sessionSettings);


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
    $token = $sessionSvc->Create($userID)->sessionToken;
    $beforeLogout = $request($m_get, $p_id, $token);
    //var_dump($beforeLogout);

    AssertTest(
        $beforeLogout['status'] === 200,
        'Active session authenticates before log-out'
    );

    AssertTest(
        ($beforeLogout['body']['data']['id'] ?? null) === (string)$userID,
        'Cookie Authentication returns the correct user ID'
    );

    AssertTest(
        ($beforeLogout['body']['data']['username'] ?? null) === $username,
        'Cookie Authentication returns the correct user name'
    );

    AssertTest(
        ($beforeLogout['body']['data']['email'] ?? null) === $email,
        'Cookie Authentication returns the correct E-Mail'
    );

    $logout = $request($m_post, $p_logout, $token);

    AssertTest(
        $logout['status'] === 200,
        'Active session can log-out'
    );


    // 2.  The old token must no longer authenticate
    $afterLogout = $request($m_get, $p_id, $token);

    AssertTest(
        $afterLogout['status'] === 401,
        'Revoked token cannot authenticate'
    );


    // 3.  Log-out without a cookie still succeeds
    $missingCookie = $request($m_post, $p_logout);

    AssertTest(
        $missingCookie['status'] === 200,
        'Missing cookie is handled successfully'
    );


    // 4.  Log-out with a malformed token still succeeds
    $malformed = $request($m_post, 'api/logout', 'invalid-token');

    AssertTest(
        $malformed['status'] === 200,
        'Malformed token is handled successfully'
    );


    // 5.  Repeating log-out with the old token is safe
    $repeatedLogout = $request($m_post, $p_logout, $token);

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

    // 7. Bearer Token returns the correct User data
    $bearerToken = $sessionSvc->Create((string)$userID)->sessionToken;
    $beforeBearerLogout = $request(
        $m_get, $p_id, null, $bearerToken
    );

    AssertTest(
        $beforeBearerLogout['status'] === 200,
        'Bearer session authenticates before Log Out'
    );

    AssertTest(
        ($beforeBearerLogout['body']['data']['id'] ?? null) === (string)$userID,
        'Bearer Authentication returns the correct user ID'
    );

    AssertTest(
        ($beforeBearerLogout['body']['data']['username'] ?? null) === $username,
        'Bearer Authentication returns the correct user name'
    );

    AssertTest(
        ($beforeBearerLogout['body']['data']['email'] ?? null) === $email,
        'Bearer Authentication returns the correct E-Mail'
    );

    // 8. Bearer Token transport can Log Out
    $bearerLogout = $request(
        $m_post, $p_logout, null, $bearerToken
    );

    AssertTest(
        $bearerLogout['status'] === 200,
        'Bearer session can Log Out'
    );

    // 9. Bearer Token cannot be reused after revocation
    $afterBearerLogout = $request(
        $m_get, $p_id, null, $bearerToken
    );

    AssertTest(
        $afterBearerLogout['status'] === 401,
        'Revoked Bearer Token cannot authenticate'
    );

    // 10. Bearer Token Transport does not modify cookies
    AssertTest(
        !HasResponseHeader($bearerLogout['headers'], 'Set-Cookie'),
        'Bearer Log Out does not set or clear cookies'
    );

    // 11. Supplying both cookies and bearer token credentials is rejected
    $cookieTokenBoth = $sessionSvc->Create((string)$userID)->sessionToken;
    $bearerTokenBoth = $sessionSvc->Create((string)$userID)->sessionToken;

    $bothCredentials = $request(
        $m_get, $p_id,
        $cookieTokenBoth,
        $bearerTokenBoth
    );

    AssertTest(
        $bothCredentials['status'] === 400,
        'Supplying both cookie and bearer credentials returns HTTP 400 (rejected)'
    );

    // 12. Missing credentials cannot access a protected route
    $missingCredentials = $request($m_get, $p_id);

    AssertTest(
        $missingCredentials['status'] === 401,
        'Missing credentials are rejected by protected routes'
    );

    // 13. Unsupported authorization scheme cannot authenticate
    $unsupportedScheme = stream_context_create([
        'http' => [
            'method' => $m_get,
            'ignore_errors' => true,
            'header' => 'Authorization: Basic randomGarbage'
        ]
    ]);

    $unsupportedBody = file_get_contents(
        'http://userbase/api/id',
        false,
        $unsupportedScheme
    );

    $unsupportedHeaders = $http_response_header ?? [];

    preg_match('/\b(\d{3})\b/', $unsupportedHeaders[0] ?? '', $matches);
    $unsupportedStatus = (int)($matches[1] ?? 0);

    AssertTest(
        $unsupportedStatus === 401,
        'Unsupported authorization scheme is rejected'
    );

    // 14. Invalid / Unknown Session Token cannot authenticate
    $invalidToken = $request($m_get, $p_id, 'invalid-session-token');

    AssertTest(
        $invalidToken['status'] === 401,
        'Unknown / Invalid Token is rejected'
    );


    // 15. Expired Session is rejected
    $expiredToken = $sessionSvc->Create((string)$userID)->sessionToken;

    // Match the SHA-256 binary hash stored by UserBase
    $tokenHash = TokenUtility::TokenToHash($expiredToken, true);

    $sql = $pdo->prepare(
        'UPDATE auth_sessions
         SET expires_at = :expires_at
         WHERE session_token_hash = :session_token_hash'
    );

    $sql->execute([
        'expires_at' => '2000-01-01 00:00:00',
        'session_token_hash' => $tokenHash
    ]);

    AssertTest(
        $sql->rowCount() === 1,
        'Test session expiration was successfully updated'
    );

    $expiredSession = $request($m_get, $p_id, $expiredToken);

    AssertTest(
        $expiredSession['status'] === 401,
        'Expired session is rejected by protected routes'
    );


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