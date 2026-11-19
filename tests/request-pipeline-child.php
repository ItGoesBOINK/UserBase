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
use UserBase\Security\RateLimit\RateLimitSubjectType;
use UserBase\Security\RateLimit\ClientIPResolver;

// Determine authentication state from command-line argument
$authenticated = ($argv[1] ?? '') === 'authenticated';

$authentication = $authenticated
    ? AuthenticationContext::Authenticated('test-user-123', SessionCredentialType::Cookie)
    : AuthenticationContext::Anonymous();

    $clock = new SystemClock();
    $id_clientIP = RateLimitSubjectType::ClientIP->name;
    $policyKey = 'test:pipeline';
    $ip = '192.0.2.100';

    $rateLimitManager = new RateLimitManager($config);
    $rateLimitManager->RegisterResolver(
        $id_clientIP,
        new ClientIPResolver()
    );
    $rateLimitManager->AddPolicy(
        $policyKey, 100, 300,
        $id_clientIP
    );

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

    register_shutdown_function(
        function () use ($authenticator, $controller): void {
            $responseCode = http_response_code();
            if ($responseCode !== 429) return;

            fwrite(
                STDERR,
                json_encode([
                    'status' => $responseCode,
                    'headers' => headers_list(),
                    'authenticatorCalled' => $authenticator->wasCalled,
                    'controllerExecuted' => $controller->wasExecuted
                ], JSON_THROW_ON_ERROR)
            );
        }
    );

    $policy = new RoutePolicy(AuthenticationRequirement::Required, rateLimits: [$policyKey]);
    $route = new Route($controller, $policy);

    $context = RequestContext::GetContext($ip, 'GET', '/api/test');

    //var_dump($authentication->IsAuthenticated());
    $pipeline->Process($route, $context);

    // Reaching this point means the controller executed
    echo json_encode(['executed' => $controller->wasExecuted]);
?>