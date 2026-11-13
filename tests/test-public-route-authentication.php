<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use UserBase\Http\AuthenticationContext;
use UserBase\Http\AuthenticationRequirement;
use UserBase\Http\RequestContext;
use UserBase\Http\RequestPipeline;
use UserBase\Http\Route;
use UserBase\Http\RoutePolicy;
use UserBase\Http\IRequestHandler;
use UserBase\Repository\AuthSessionRepository;
use UserBase\Security\Authentication\SessionAuthenticator;
use UserBase\Security\Authentication\SessionCredentialResolver;
use UserBase\Security\Cookies\SessionCookie;
use UserBase\Service\SessionService;
use UserBase\Service\CSRFTokenService;
use UserBase\Time\SystemClock;

// Authentication Dependencies
$clock = new SystemClock();
$authSessionRepo = new AuthSessionRepository($pdo);
$sessionService = new SessionService(
    $authSessionRepo,
    $clock,
    $sessionSettings
);

$cookie = new SessionCookie(false, $sessionSettings);
$credentialResolver = new SessionCredentialResolver($cookie);

$authenticator = new SessionAuthenticator(
    $sessionService,
    $credentialResolver
);

$tokenService = new CSRFTokenService($config);
$pipeline = new RequestPipeline($authenticator, $tokenService);

// Test handler captures the resulting RequestContext
$handler = new class implements IRequestHandler
{
    public ?RequestContext $receivedContext = null;

    public function Execute(RequestContext $reqContext): void
    { $this->receivedContext = $reqContext; }
};


// Construct a public route
$policy = new RoutePolicy(AuthenticationRequirement::Public);
$route = new Route($handler, $policy);

// Construct a request without credentials
$reqContext = new RequestContext('GET', '/api/test', [], []);

$pipeline->Process($route, $reqContext);

AssertTest(
    $handler->receivedContext !== null,
    'Public route handler was executed'
);

AssertTest(
    !$handler->receivedContext->authContext->IsAuthenticated(),
    'Public route receives an anonymous authentication context'
);

AssertTest(
    $handler->receivedContext->authContext->userID === null,
    'Anonymous authentication context contains no user ID'
);

HandleComplete(__FILE__);

?>