<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestController.php';
require_once __DIR__ . '/MockAuthenticator.php';

use UserBase\Http\AuthenticationContext;
use UserBase\Http\AuthenticationRequirement;
use UserBase\Http\RequestContext;
use UserBase\Http\RequestPipeline;
use UserBase\Http\Route;
use UserBase\Http\RoutePolicy;
use UserBase\Tests\MockAuthenticator;
use UserBase\Tests\TestController;

// Determine authentication state from command-line argument
$authenticated = ($argv[1] ?? '') === 'authenticated';

$authentication = $authenticated
    ? AuthenticationContext::Authenticated('test-user-123')
    : AuthenticationContext::Anonymous();

    $authenticator = new MockAuthenticator($authentication);
    $pipeline = new RequestPipeline($authenticator);

    $controller = new TestController();
    $policy = new RoutePolicy(AuthenticationRequirement::Required);
    $route = new Route($controller, $policy);

    $context = new RequestContext('GET', '/api/test', [], []);

    //var_dump($authentication->IsAuthenticated());
    $pipeline->Process($route, $context);

    // Reaching this point means the controller executed
    echo json_encode(['executed' => $controller->wasExecuted]);
?>