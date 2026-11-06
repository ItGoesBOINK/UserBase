<?php

declare(strict_types=1);

namespace UserBase\Core;

use PDO;
use RuntimeException;
use UserBase\Mail\PHPMailerEmailSender;

use UserBase\Time\IClock;
use UserBase\Config\Configuration;
use UserBase\Config\SessionSettings;

use UserBase\Http\ExceptionHandler;
use UserBase\Http\RouteDescriptor;
use UserBase\Http\RouteModule;
use UserBase\Http\AuthenticationRequirement;

use UserBase\Controller\HealthController;
use UserBase\Controller\MeController;
use UserBase\Controller\LoginController;
use UserBase\Controller\LogOutController;
use UserBase\Controller\RegistrationController;
use UserBase\Controller\EmailVerificationController;

use UserBase\Service\LoginService;
use UserBase\Service\SessionService;
use UserBase\Service\RegistrationService;
use UserBase\Service\EmailVerificationService;

use UserBase\Repository\UserRepository;
use UserBase\Repository\AuthSessionRepository;
use UserBase\Repository\EmailVerificationTokenRepository;

use UserBase\Security\Cookies\SessionCookie;
use UserBase\Security\Authentication\SessionCredentialResolver;

class DefaultRouteModule extends RouteModule
{
    // Controllers
    protected readonly HealthController $ctrlHealth;
    protected readonly MeController $ctrlMe;
    protected readonly LoginController $ctrlLogin;
    protected readonly LogOutController $ctrlLogout;
    protected readonly RegistrationController $ctrlRegistration;
    protected readonly EmailVerificationController $ctrlVerification;

    // Services
    protected readonly LoginService $serviceLogin;
    protected readonly RegistrationService $serviceRegistration;
    protected readonly EmailVerificationService $serviceEmailVerification;

    // Repositories
    protected readonly UserRepository $userRepo;
    protected readonly EmailVerificationTokenRepository $verificationTokenRepo;

    // E-Mail
    protected readonly PHPMailerEmailSender $emailSender;

    public function __construct(
        Configuration $config,
        IClock $clock,
        PDO $connection,
        SessionService $sessionService,
        SessionCookie $cookie,
        SessionCredentialResolver $credentialResolver
    ) {
        $this->InitDefaultRoutes(
            $config,
            $clock,
            $connection,
            $sessionService,
            $cookie,
            $credentialResolver
        );
    }

    public function InitDefaultRoutes(
        Configuration $config,
        IClock $clock,
        PDO $connection,
        SessionService $sessionService,
        SessionCookie $cookie,
        SessionCredentialResolver $credentialResolver
    ): void {
        $get = 'GET';
        $post = 'POST';

        $pub = AuthenticationRequirement::Public;
        $req = AuthenticationRequirement::Required;

        $p_health   = '/api/health';
        $p_me       = '/api/me';
        $p_login    = '/api/login';
        $p_logout   = '/api/logout';
        $p_register = '/api/register';
        $p_verify   = '/api/email-verifications/confirm';

        $this->emailSender = new PHPMailerEmailSender($config);

        $this->userRepo = new UserRepository($connection);
        $this->verificationTokenRepo = new EmailVerificationTokenRepository($connection);

        $this->serviceLogin = new LoginService($this->userRepo, $sessionService);

        $this->serviceEmailVerification = new EmailVerificationService(
            $connection,
            $this->userRepo,
            $this->verificationTokenRepo,
            $clock
        );

        $this->serviceRegistration = new RegistrationService(
            $this->userRepo,
            $this->serviceEmailVerification,
            $this->emailSender,
            $config
        );

        $this->ctrlHealth =
            new HealthController();
        $this->ctrlMe =
            new MeController($this->userRepo);
        $this->ctrlRegistration =
            new RegistrationController($this->serviceRegistration);
        $this->ctrlLogin =
            new LoginController($this->serviceLogin, $cookie, $sessionService);
        $this->ctrlLogout =
            new LogOutController($sessionService, $credentialResolver, $cookie);
        $this->ctrlVerification =
            new EmailVerificationController($this->serviceEmailVerification);

        $this->descriptors = [
            new RouteDescriptor($get,  $p_health,   $pub, $this->ctrlHealth),
            new RouteDescriptor($get,  $p_me,       $req, $this->ctrlMe),
            new RouteDescriptor($post, $p_login,    $pub, $this->ctrlLogin),
            new RouteDescriptor($post, $p_logout,   $pub, $this->ctrlLogout),
            new RouteDescriptor($post, $p_register, $pub, $this->ctrlRegistration),
            new RouteDescriptor($post, $p_verify,   $pub, $this->ctrlVerification),
        ];
    }

}

?>