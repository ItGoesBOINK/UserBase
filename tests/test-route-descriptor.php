<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use Throwable;
use UserBase\Http\ExceptionHandler;
use UserBase\Http\AuthenticationRequirement;
use UserBase\Http\IRequestHandler;
use UserBase\Http\RequestContext;
use UserBase\Http\RouteDescriptor;

// Test IRequestHandler
class TestRouteDescriptorHandler implements IRequestHandler
{
    public function Execute(RequestContext $reqContext): void {}
}


$handler = new TestRouteDescriptorHandler();


// 1. Basic configuration retention
$descriptor = new RouteDescriptor(
    'GET', '/api/test',
    AuthenticationRequirement::Required,
    $handler
);

AssertTest(
    $descriptor->method === 'GET',
    'RouteDescriptor stores the HTTP method'
);

AssertTest(
    $descriptor->path === '/api/test',
    'RouteDescriptor stores the route path'
);

AssertTest(
    $descriptor->authReq === AuthenticationRequirement::Required,
    'RouteDescriptor stores the authentication requirement'
);

AssertTest(
    $descriptor->handler === $handler,
    'RouteDescriptor stores the handler reference'
);


// 2. Type Enforcement

$get = 'GET';
$path = '/api/test';
$req = AuthenticationRequirement::Required;

AssertTypeError(
    fn() => new RouteDescriptor(123, $path, $req, $handler),
    'RouteDescriptor rejects an invalid method type'
);

AssertTypeError(
    fn() => new RouteDescriptor($get, 123, $req, $handler),
    'RouteDescriptor rejects an invalid path'
);

AssertTypeError(
    fn() => new RouteDescriptor($get, $path, 'Required', $handler),
    'RouteDescriptor rejects an invalid authentication requirement type'
);

AssertTypeError(
    fn() => new RouteDescriptor($get, $path, $req, new stdClass()),
    'RouteDescriptor rejects an invalid handler type'
);




// 3. Input Validation
AssertInvalidArgumentException(
    fn() => new RouteDescriptor('HACK', $path, $req, $handler),
    'RouteDescriptor rejects an invalid method name'
);

HandleComplete(__FILE__);


?>