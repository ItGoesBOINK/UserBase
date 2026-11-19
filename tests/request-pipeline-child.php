<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestController.php';
require_once __DIR__ . '/MockAuthenticator.php';

use UserBase\Time\SystemClock;
use UserBase\Service\CSRFTokenService;
use UserBase\Service\RateLimitService;
use UserBase\Repository\RateLimitRepository;
use UserBase\Http\AuthenticationContext;
use UserBase\Http\AuthenticationRequirement;
use UserBase\Http\RequestContext;
use UserBase\Http\RequestPipeline;
use UserBase\Http\Route;
use UserBase\Http\RoutePolicy;
use UserBase\Tests\MockAuthenticator;
use UserBase\Tests\TestController;
use UserBase\Security\Authentication\SessionCredentialType;
use UserBase\Security\RateLimit\RateLimitManager;

// Determine authentication state from command-line argument
$authenticated = ($argv[1] ?? '') === 'authenticated';

$authentication = $authenticated
    ? AuthenticationContext::Authenticated('test-user-123', SessionCredentialType::Cookie)
    : AuthenticationContext::Anonymous();

    $clock = new SystemClock();
    $rateLimitManager = new RateLimitManager($config);
    $rateLimitRepo = new RateLimitRepository($pdo);
    $rateLimitService = new RateLimitService($rateLimitRepo, $clock, $config->rateLimitKey);
    $tokenService = new CSRFTokenService($config);
    $authenticator = new MockAuthenticator($authentication);
    $pipeline = new RequestPipeline(
        $authenticator,
        $tokenService,
        $rateLimitManager,
        $rateLimitService,
        $clock
    );

    $controller = new TestController();
    $policy = new RoutePolicy(AuthenticationRequirement::Required);
    $route = new Route($controller, $policy);

    $context = RequestContext::GetContext(null, 'GET', '/api/test');

    //var_dump($authentication->IsAuthenticated());
    $pipeline->Process($route, $context);

    // Reaching this point means the controller executed
    echo json_encode(['executed' => $controller->wasExecuted]);
?>