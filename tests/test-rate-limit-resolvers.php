<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use UserBase\Http\RequestContext;
use UserBase\Security\RateLimit\ClientIPResolver;
use UserBase\Security\RateLimit\AccountIdentifierResolver;

$m_get = 'GET';
$m_post = 'POST';


// Client IP Resolver

$ipResolver = new ClientIPResolver();
$uri = '/login';
$ip = '192.0.2.10';

$reqContext = new RequestContext(
    $ip, $m_post, $uri,
    [], [], []
);

AssertTest(
    $ipResolver->Resolve($reqContext) === $ip,
    'Client IP Resolver resolves IPv4 addresses'
);

$ip = '2001:db8::1';

$reqContext = new RequestContext(
    $ip, $m_post, $uri,
    [], [], []
);

AssertTest(
    $ipResolver->Resolve($reqContext) === $ip,
    'Client IP Resolver resolves IPv6 addresses'
);

$ip = 'invalid-ip';

$reqContext = new RequestContext(
    $ip, $m_post, $uri,
    [], [], []
);

$thrown = false;

try   { $ipResolver->Resolve($reqContext); }
catch (RuntimeException $e) { $thrown = true; }

AssertTest(
    $thrown,
    'Client IP Resolver rejects invalid IP addresses'
);


// Account Identifier Resolver

$accountResolver = new AccountIdentifierResolver();

$ip = '192.0.2.10';

$reqContext = new RequestContext(
    $ip, $m_post, $uri,
    [], [], ['username' => 'TestUser']
);

AssertTest(
    $accountResolver->Resolve($reqContext) === 'testuser',
    'Account Identifier Resolver normalizes user names'
);

// Missing Username
$reqContext = new RequestContext(
    $ip, $m_post, $uri,
    [], [], []
);

$thrown = false;

try {
    $result = $accountResolver->Resolve($reqContext);
    //echo "Result: {$result}\n";
} catch (RuntimeException $e) { $thrown = true; }

AssertTest(
    $thrown || $result === null,
    'Account Identifier Resolver rejects missing user names'
);

// Non-String User-Name
$reqContext = new RequestContext(
    $ip, $m_post, $uri,
    [], [], ['username' => 12345]
);

$thrown = false;

try   {
    $result = $accountResolver->Resolve($reqContext);
} catch (RuntimeException $e) { $thrown = true; }

AssertTest(
    $thrown || $result === null,
    'Account Identifier Resolver rejects non-string user names'
);


// Empty User-Name
$reqContext = new RequestContext(
    $ip, $m_post, $uri,
    [], [], ['username' => '']
);

$thrown = false;

try   { $result = $accountResolver->Resolve($reqContext); }
catch (RuntimeException $e) { $thrown = true; }

AssertTest($thrown || $result === null,
'Account Identifier Resolver rejects empty user names');

HandleComplete(__FILE__);





?>