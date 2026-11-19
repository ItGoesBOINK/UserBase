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
use UserBase\Service\CSRFTokenService;
use UserBase\Service\RateLimitService;
use UserBase\Security\Authentication\SessionCredentialType;
use UserBase\Security\RateLimit\RateLimitManager;
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
    $policy = new RoutePolicy($authReq);
    $route = new Route($controller, $policy);
    $reqContext = RequestContext::GetContext(null, 'GET', '/api/test');

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

HandleComplete(__FILE__);

?>