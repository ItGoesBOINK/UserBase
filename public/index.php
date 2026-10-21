<?php

declare(strict_types=1);

use UserBase\Config\Configuration;
use UserBase\Database\DatabaseConnection;
use UserBase\Http\ExceptionHandler;
use UserBase\Http\JsonResponse;
use UserBase\Http\Router;
use UserBase\Repository\EmailVerificationTokenRepository;
use UserBase\Repository\UserRepository;
use UserBase\Service\RegistrationService;
use UserBase\Service\EmailVerificationService;
use UserBase\Service\IEmailSender;
use UserBase\Mail\PHPMailerEmailSender;
use UserBase\Time\SystemClock;
use UserBase\Controller\HealthController;
use UserBase\Controller\RegistrationController;
use UserBase\Controller\EmailVerificationController;

header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/src/bootstrap.php';

    // Initialize Connection and Repositories
    $clock = new SystemClock();
    $connection = (new DatabaseConnection($config))->GetConnection();
    $users = new UserRepository($connection);
    $tokens = new EmailVerificationTokenRepository($connection);

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

    // Build Router
    $healthCtrl =
    new HealthController();

    $regCtrl =
    new RegistrationController($registration);

    $verificationCtrl =
    new EmailVerificationController($emailVerification);

    $router = new Router();

    // Health Check
    $router->Add(
        'GET', '/api/health',
        [$healthCtrl, 'GetStatus']
    );

    // Register New Account
    $router->Add(
        'POST', '/api/register',
        [$regCtrl, 'Register']
    );

    // Verify E-Mail Address for New Account
    $router->Add(
        'POST', '/api/email-verifications/confirm',
        [$verificationCtrl, 'Verify']
    );

    // --> Dispatch Route
    try {
        $router->Dispatch(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URL'] ?? '/'
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