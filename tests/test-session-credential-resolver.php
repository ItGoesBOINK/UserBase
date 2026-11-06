<?php

declare(strict_types=1);

// use Throwable;
use UserBase\Config\SessionSettings;
use UserBase\Http\RequestContext;
use UserBase\Security\Authentication\SessionCredentialResolver;
use UserBase\Security\Authentication\SessionCredentialType;
use UserBase\Security\Cookies\SessionCookie;

require_once __DIR__ . '/test_base.php';

$sessionName = 'userbase_session';
$settings = new SessionSettings(
    cookieName: $sessionName,
    cookiePath: '/',
    sameSitePolicy: 'Lax',
    httpOnly: true,
    lifetimeSeconds: 86400
);

$cookie = new SessionCookie($config->IsSecure(), $settings);
$resolver = new SessionCredentialResolver($cookie);

function CreateRequest(
    array $headers = [],
    array $cookies = []
): RequestContext {
    return new RequestContext(
        method: 'GET',
        requestURI: '/api/test',
        headers: $headers,
        cookies: $cookies
    );
}

// 1. Cookie Credential
$request = CreateRequest( cookies: ['userbase_session' => 'cookie-token'] );
$credential = $resolver->Resolve($request);

AssertTest(
    $credential?->token === 'cookie-token' &&
    $credential->type === SessionCredentialType::Cookie,
    'Resolves Cookie credential'
);


// 2. Bearer Credential
$request = CreateRequest( headers: ['authorization' => 'Bearer bearer-token'] );
$credential = $resolver->Resolve($request);

AssertTest(
    $credential?->token === 'bearer-token' &&
    $credential->type === SessionCredentialType::Bearer,
    'Resolves Bearer credential'
);


// 3. Case-insensitive Bearer scheme
$request = CreateRequest( headers: ['authorization' => 'bearer lowercase-token'] );
$credential = $resolver->Resolve($request);

AssertTest(
    $credential?->token === 'lowercase-token' &&
    $credential->type === SessionCredentialType::Bearer,
    'Resolves case-insensitive Bearer scheme'
);


// 4. No Credential
$request = CreateRequest();

AssertTest(
    $resolver->Resolve($request) == null,
    'Returns null when no credential is supplied'
);


// 5. Conflicting Credentials
$request = CreateRequest(
    headers: ['authorization' => 'Bearer bearer-token'],
    cookies: ['userbase_session' => 'cookie-token']
);

$throws = false;

try {
    $resolver->Resolve($request);
} catch (Throwable $err) {
    // var_dump($err);
    $throws = true;
}

AssertTest(
    $throws, 'Rejects simultaneous Cookie and Bearer credentials'
);


// 7. Unsupported Authorization Scheme
$request = CreateRequest( headers: ['authorization' => 'Basic some-token'] );

AssertTest(
    $resolver->Resolve($request) === null,
    'Ignores unsupported authorization scheme'
);


// 8. Malformed Bearer Header
$request = CreateRequest( headers: ['authorization' => 'Bearer'] );

AssertTest(
    $resolver->Resolve($request) === null,
    'Ignores malformed Bearer Header'
);

HandleComplete(__FILE__);

?>