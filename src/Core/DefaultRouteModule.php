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
use UserBase\Controller\IDController;
use UserBase\Controller\LoginController;
use UserBase\Controller\LogOutController;
use UserBase\Controller\RegistrationController;
use UserBase\Controller\EmailVerificationController;

use UserBase\Service\LoginService;
use UserBase\Service\SessionService;
use UserBase\Service\RegistrationService;
use UserBase\Service\EmailVerificationService;
use UserBase\Service\CSRFTokenService;

use UserBase\Repository\UserRepository;
use UserBase\Repository\AuthSessionRepository;
use UserBase\Repository\EmailVerificationTokenRepository;

use UserBase\Security\Cookies\SessionCookie;
use UserBase\Security\Authentication\SessionCredentialResolver;
use UserBase\Security\RateLimit\RateLimitManager;
use UserBase\Security\RateLimit\RateLimitSubjectType;

class DefaultRouteModule extends RouteModule
{
    // Controllers
    protected readonly HealthController $ctrlHealth;
    protected readonly IDController $ctrlID;
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
        CSRFTokenService $csrfTokenService,
        SessionCookie $cookie,
        SessionCredentialResolver $credentialResolver,
        RateLimitManager $rateLimitManager
    ) {
        $this->InitDefaultRoutes(
            $config,
            $clock,
            $connection,
            $sessionService,
            $csrfTokenService,
            $cookie,
            $credentialResolver,
            $rateLimitManager
        );
    }

    public function InitDefaultRoutes(
        Configuration $config,
        IClock $clock,
        PDO $connection,
        SessionService $sessionService,
        CSRFTokenService $csrfTokenService,
        SessionCookie $cookie,
        SessionCredentialResolver $credentialResolver,
        RateLimitManager $rateLimitManager
    ): void {

        $pub = AuthenticationRequirement::Public;
        $req = AuthenticationRequirement::Required;

        $get = 'GET';
        $post = 'POST';
        $api = '/api/';

        $p_health   = $api . 'health';
        $p_id       = $api . 'id';
        $p_login    = $api . 'login';
        $p_logout   = $api . 'logout';
        $p_register = $api . 'register';
        $p_verify   = $api . 'verify';

        $policyKey_loginIP = 'login:ip';
        $policyKey_loginAccount = 'login:account';
        $policies_login = [$policyKey_loginIP, $policyKey_loginAccount];

        $rateLimitManager->AddPolicy(
            $policyKey_loginIP, 10, 300,
            RateLimitSubjectType::ClientIP->name
        );

        $rateLimitManager->AddPolicy(
            $policyKey_loginAccount, 10, 300,
            RateLimitSubjectType::AccountIdentifier->name
        );

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



        $this->ctrlID =
            new IDController(
                $this->userRepo,
                $csrfTokenService,
                $cookie
            );

        $this->ctrlRegistration =
            new RegistrationController
                ($this->serviceRegistration);

        $this->ctrlLogin =
            new LoginController(
                $this->serviceLogin,
                $cookie,
                $sessionService,
                $csrfTokenService
            );

        $this->ctrlLogout =
            new LogOutController
            (
                $sessionService,
                $credentialResolver,
                $cookie
            );

        $this->ctrlVerification =
            new EmailVerificationController
                ($this->serviceEmailVerification);

        $this->descriptors = [
            new RouteDescriptor($get,  $p_health,   $pub, $this->ctrlHealth,       false, []),
            new RouteDescriptor($get,  $p_id,       $req, $this->ctrlID,           false, []),
            new RouteDescriptor($post, $p_login,    $pub, $this->ctrlLogin,        false, $policies_login),
            new RouteDescriptor($post, $p_logout,   $pub, $this->ctrlLogout,       true,  []),
            new RouteDescriptor($post, $p_register, $pub, $this->ctrlRegistration, false, []),
            new RouteDescriptor($post, $p_verify,   $pub, $this->ctrlVerification, false, []),
        ];
    }

}

?>