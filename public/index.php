<?php

declare(strict_types=1);

use UserBase\Mail\PHPMailerEmailSender;

use UserBase\Time\SystemClock;
use UserBase\Config\Configuration;
use UserBase\Database\DatabaseConnection;

use UserBase\Http\ExceptionHandler;
use UserBase\Http\JsonResponse;
use UserBase\Http\Router;
use UserBase\Http\Route;
use UserBase\Http\RoutePolicy;
use UserBase\Http\RequestContext;
use UserBase\Http\RequestPipeline;
use UserBase\Http\AuthenticationRequirement;
use UserBase\Http\CorsHandler;
use UserBase\Http\OriginValidator;

use UserBase\Repository\EmailVerificationTokenRepository;
use UserBase\Repository\UserRepository;
use UserBase\Repository\AuthSessionRepository;

use UserBase\Service\RegistrationService;
use UserBase\Service\EmailVerificationService;
use UserBase\Service\IEmailSender;
use UserBase\Service\LoginService;
use UserBase\Service\SessionService;

use UserBase\Controller\HealthController;
use UserBase\Controller\RegistrationController;
use UserBase\Controller\EmailVerificationController;
use UserBase\Controller\LoginController;
use UserBase\Controller\MeController;
use UserBase\Controller\LogOutController;


header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/src/bootstrap.php';

    CorsHandler::Handle($config);
    OriginValidator::Validate($config);

    // Initialize Connection and Repositories
    $clock = new SystemClock();
    $connection = (new DatabaseConnection($config))->GetConnection();
    $users = new UserRepository($connection);
    $tokens = new EmailVerificationTokenRepository($connection);
    $authSessions = new AuthSessionRepository($connection);

    // Build Services
    $emailSender = new PHPMailerEmailSender($config);

    $emailVerification = new EmailVerificationService(
        $connection,
        $users,
        $tokens,
        $clock
    );

    $registration = new RegistrationService(
        $users,
        $emailVerification,
        $emailSender,
        $config
    );

    $sessions = new SessionService($authSessions, $clock);
    $login = new LoginService($users, $sessions);


    // Build Router
    $healthCtrl =
    new HealthController();

    $regCtrl =
    new RegistrationController($registration);

    $verificationCtrl =
    new EmailVerificationController($emailVerification);

    $loginCtrl =
    new LoginController($login);

    $meCtrl =
    new MeController($sessions, $users);

    $logoutCtrl =
    new LogOutController($sessions);

    $pipeline = new RequestPipeline();
    $router = new Router($pipeline);

    // Health Check
    $router->Add(
        'GET', '/api/health',
        new Route(
            $healthCtrl,
            new RoutePolicy(AuthenticationRequirement::Public))
    );

    // Get Current Authenticated User
    $router->Add(
        'GET', '/api/me',
        new Route(
            $meCtrl,
            new RoutePolicy(AuthenticationRequirement::Required))
    );

    // Login
    $router->Add(
        'POST', '/api/login',
        new Route(
            $loginCtrl,
            new RoutePolicy(AuthenticationRequirement::Public))
    );

    // Log-Out
    $router->Add(
        'POST', '/api/logout',
        new Route(
            $logoutCtrl,
            new RoutePolicy(AuthenticationRequirement::Public))
    );

    // Register New Account
    $router->Add(
        'POST', '/api/register',
        new Route(
            $regCtrl,
            new RoutePolicy(AuthenticationRequirement::Public))
    );

    // Verify E-Mail Address for New Account
    $router->Add(
        'POST', '/api/email-verifications/confirm',
        new Route(
            $verificationCtrl,
            new RoutePolicy(AuthenticationRequirement::Public))
    );

    // --> Dispatch Route
    $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri     = $_SERVER['REQUEST_URI']    ?? '/';
    $context = new RequestContext(
        $method,
        $uri,
        array_change_key_case(getallheaders() ?: [], CASE_LOWER)
    );

    //$router->Dispatch($method, $uri);
    $router->Dispatch($context);


} catch (Throwable $e) {
    ExceptionHandler::Handle($e);
}

?>