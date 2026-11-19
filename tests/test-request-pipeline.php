<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestController.php';
require_once __DIR__ . '/MockAuthenticator.php';

use UserBase\Time\SystemClock;
use UserBase\Config\Configuration;

use UserBase\Http\AuthenticationContext;
use UserBase\Http\AuthenticationRequirement;
use UserBase\Http\RequestContext;
use UserBase\Http\RequestPipeline;
use UserBase\Http\Route;
use UserBase\Http\RoutePolicy;
use UserBase\Http\APIErrors;

use UserBase\Service\CSRFTokenService;
use UserBase\Service\RateLimitService;

use UserBase\Security\Authentication\SessionCredentialType;
use UserBase\Security\RateLimit\RateLimitManager;
use UserBase\Security\RateLimit\RateLimitSubjectType;
use UserBase\Security\RateLimit\RateLimitPolicy;
use UserBase\Security\RateLimit\ClientIPResolver;
use UserBase\Security\RateLimit\AccountIdentifierResolver;

use UserBase\Repository\RateLimitRepository;

use UserBase\Tests\MockAuthenticator;
use UserBase\Tests\TestController;

function RunPipelineChild(string $authentication): array
{
    
    $command = [
        'php',
        __DIR__ . '/request-pipeline-child.php',
        $authentication
    ];

    $process = proc_open(
        $command,
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ],
        $pipes
    );

    if (!is_resource($process))
    { throw new RuntimeException('Unable to start child process.'); }

    fclose($pipes[0]);

    $output = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'output' => $output,
        'errors' => $errors,
        'exitCode' => $exitCode
    ];
}

function TestRoutePolicy(
    Configuration $config,
    PDO $connection,
    AuthenticationRequirement $authReq,
    AuthenticationContext $authContext,
    bool $expectedExecution,
    string $description
): void {

    $clock = new SystemClock();
    $tokenService = new CSRFTokenService($config);

    $rateLimitManager = new RateLimitManager($config);
    $id_clientIP = RateLimitSubjectType::ClientIP->name;
    $policyKey = 'test:pipeline';
    $ip = '192.0.2.100';

    $rateLimitManager->RegisterResolver(
        $id_clientIP,
        new ClientIPResolver()
    );

    $rateLimitManager->AddPolicy(
        $policyKey, 100, 300, $id_clientIP
    );

    $policy = new RoutePolicy($authReq, rateLimits: [$policyKey]);



    $authenticator = new MockAuthenticator($authContext);
    $rateLimitRepo = new RateLimitRepository($connection);
    $rateLimitService = new RateLimitService(
        $rateLimitRepo,
        $clock,
        $config->rateLimitKey
    );

    $pipeline = new RequestPipeline(
        $authenticator,
        $tokenService,
        $rateLimitManager,
        $rateLimitService,
        $clock
    );

    $controller = new TestController();
    $route = new Route($controller, $policy);

    $reqContext = RequestContext::GetContext($ip, 'GET', '/api/test');

    $pipeline->Process($route, $reqContext);

    AssertTest(
        $controller->wasExecuted === $expectedExecution,
        $description
    );
}

$anonymous = AuthenticationContext::Anonymous();
$authenticated = AuthenticationContext::Authenticated('test-user-123', SessionCredentialType::Cookie);

// Public
TestRoutePolicy(
    $config, $pdo,
    AuthenticationRequirement::Public,
    $anonymous,
    true,   // Expected behavior
    'Public route allows anonymous requests'
);

TestRoutePolicy(
    $config, $pdo,
    AuthenticationRequirement::Public,
    $authenticated,
    true,   // Expected behavior
    'Public route allows authenticated requests'
);


// Optional
TestRoutePolicy(
    $config, $pdo,
    AuthenticationRequirement::Optional,
    $anonymous,
    true,   // Expected behavior
    'Optional route allows anonymous requests'
);

TestRoutePolicy(
    $config, $pdo,
    AuthenticationRequirement::Optional,
    $authenticated,
    true,   // Expected behavior
    'Optional route allows authenticated requests'
);


// Required: Anonymous
$result = RunPipelineChild('anonymous');
$output = $result['output'];
$errors = $result['errors'];
$exitCode = $result['exitCode'];

//var_dump($result);

AssertTest(
    $exitCode === 0 &&
    str_contains($output, '"code":"AUTHENTICATION_REQUIRED"') &&
    !str_contains($output, '"executed":true') &&
    $errors === '',
    'Required route rejects anonymous requests'
);

// Required: Authenticated
$result = RunPipelineChild('authenticated');
$output = $result['output'];
$errors = $result['errors'];
$exitCode = $result['exitCode'];

//var_dump($result);
$response = json_decode($result['output'], true, flags: JSON_THROW_ON_ERROR);

AssertTest(
    $exitCode === 0 &&
    isset($response['executed']) &&
    $response['executed'] === true &&
    $errors === '',
    'Required route allows authenticated requests'
);


// Rate-Limit: Rejected RequestContext

$ip = '192.0.2.100';
$policyKey = 'test:pipeline';
$id_clientIP = RateLimitSubjectType::ClientIP->name;
$clock = new SystemClock();
$limit = 100;
$interval = 300;

$policy = new RateLimitPolicy($policyKey, $limit, $interval);

//$rateLimitManager = new RateLimitManager($config);
$rateLimitRepo = new RateLimitRepository($pdo);
$rateLimitService = new RateLimitService(
    $rateLimitRepo,
    $clock,
    $config->rateLimitKey
);

// Exhaust the policy
$decision = null;

for ($i = 0; $i < $limit; $i++) {
    $decision = $rateLimitService->Attempt($policy, $ip);
}

AssertTest(
    $decision !== null && !$decision->allowed,
    'Rate-Limit test successfully exhausts its policy'
);

// Run the child process
$result = RunPipelineChild('anonymous');
$response = json_decode(
    $result['output'],
    true,
    flags: JSON_THROW_ON_ERROR
);

//var_dump($result);
//var_dump($response);

AssertTest(
    $result['exitCode'] === 0 &&
    ($response['error']['code'] ?? null) === APIErrors::CODE__RATE_LIMITED,
    'Rate-Limited request receives a 429 error response'
);

$diagnostics = json_decode(
    $result['errors'],
    true,
    flags: JSON_THROW_ON_ERROR
);

AssertTest(
    $diagnostics['status'] === 429 &&
    $diagnostics['authenticatorCalled'] === false &&
    $diagnostics['controllerExecuted'] === false,
    'Rate-Limited request is rejected before authentication and execution'
);

/*
var_dump($diagnostics);

$hasRetryAfter = false;

foreach ($diagnostics['headers'] as $header) {
    if (preg_match('/^Retry-After:\s*[1-9]\d*$/i', $header)) {
        $hasRetryAfter = true;
        break;
    }
}

AssertTest(
    $hasRetryAfter,
    'Rate-Limited response includes a valid Retry-After header'
);
*/

/*
$rateLimitManager->RegisterResolver(
    $id_clientIP,
    new ClientIPResolver()
);

$rateLimitManager->AddPolicy(
    $policyKey, 100, 300, $id_clientIP
);
*/


HandleComplete(__FILE__);

?>