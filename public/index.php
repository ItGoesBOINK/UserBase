<?php

declare(strict_types=1);

use UserBase\Mail\PHPMailerEmailSender;

use UserBase\Time\SystemClock;
use UserBase\Config\Configuration;
use UserBase\Database\DatabaseConnection;

use UserBase\Http\ExceptionHandler;
use UserBase\Http\JsonResponse;
use UserBase\Http\Router;
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


    $router = new Router();

    // Health Check
    $router->Add(
        'GET', '/api/health',
        $healthCtrl
    );

    // Get Current Authenticated User
    $router->Add(
        'GET', '/api/me',
        $meCtrl
    );

    // Login
    $router->Add(
        'POST', '/api/login',
        $loginCtrl
    );

    // Log-Out
    $router->Add(
        'POST', '/api/logout',
        $logoutCtrl
    );

    // Register New Account
    $router->Add(
        'POST', '/api/register',
        $regCtrl
    );

    // Verify E-Mail Address for New Account
    $router->Add(
        'POST', '/api/email-verifications/confirm',
        $verificationCtrl
    );

    // --> Dispatch Route
    try {
        $router->Dispatch(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/'
        );
    } catch (\Throwable $err) {
        ExceptionHandler::Handle($err);
    }


    JsonResponse::Error(
        'not_found',
        'The requested end-point was not found',
        404
    );

} catch (Throwable $e) {
    ExceptionHandler::Handle($e);
}

?>