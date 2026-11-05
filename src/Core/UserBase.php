<?php

declare(strict_types=1);

namespace UserBase\Core;

use Throwable;
use UserBase\Http\ExceptionHandler;

try {
    require_once dirname(__DIR__) . '/src/bootstrap.php';
} catch (Throwable $e) {
    ExceptionHandler::Handle($e);
}

use PDO;

use UserBase\Database\DatabaseProxy;
use UserBase\Service\SessionService;
use UserBase\Repository\AuthSessionRepository;
use UserBase\Config\Configuration;
use UserBase\Config\SessionSettings;

use UserBase\Time\IClock;
use UserBase\Time\SystemClock;

use UserBase\Security\Cookies\SessionCookie;
use UserBase\Security\Authentication\IAuthenticator;
use UserBase\Security\Authentication\SessionAuthenticator;
use UserBase\Security\Authentication\SessionCredentialResolver;

use UserBase\Http\CorsHandler;
use UserBase\Http\OriginValidator;
use UserBase\Http\SessionCookie;
use UserBase\Http\Router;
use UserBase\Http\RequestPipeline;
use UserBase\Http\IRequestHandler;
use UserBase\Http\RequestContext;
use UserBase\Http\AuthenticationRequirement;


public class UserBase
{
    public readonly bool $isInitialized = false;

    // Core Functionality
    protected readonly IClock $clock;
    protected readonly DatabaseProxy $database;
    protected readonly PDO $connection;

    // Session Dependencies
    protected readonly SessionCookie $cookie;
    protected readonly AuthSessionRepository $sessionRepo;
    protected readonly SessionService $sessionService;
    protected readonly SessionCredentialResolver $credentialResolver;
    protected readonly IAuthenticator $sessionAuthenticator;

    // Router and Pipeline
    protected readonly RequestPipeline $pipeline;
    protected readonly Router $router;

    /** Initialization ------------------------------------ */

    public function __construct(
        public readonly Configuration $config,
        public readonly SessionSettings $sessionSettings,
        protected readonly $addDefaultRoutes = false,
        bool $initImmediately = false
    ) {
        $this->ValidateConfiguration();
        if ($initImmediately) $this->Init();
    }

    public function Init(): bool {

        try {

            $this->ValidateConfiguration();
            $this->InjectHeaders();
            $this->ApplySecurityPolicies();
            $this->InitClock();
            $this->InitDatabaseConnection();
            $this->InitSessionDependencies();
            $this->InitRouter();

            $this->isInitialized = true;

        } catch (Throwable $e) {
            $this->isInitialized = false;
            ExceptionHandler::Handle($e);
        }

        if ($this->addDefaultRoutes) $this->AddDefaultRoutes();

        return $this->isInitialized;
    }

    public function DeInit(): void {
        $this->isInitialized = false;
    }

    protected function ValidateConfiguration(): void
    {
        if ($this->config === null)
            throw new RuntimeException('UserBase Configuration not found!');

        if ($this->sessionSettings === null)
            throw new RuntimeException('UserBase Session Settings not found!');
    }

    protected function InjectHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
    }

    protected function ApplySecurityPolicies()
    {
        CorsHandler::Handle($config);
        OriginValidator::Validate($config);
    }

    protected function InitClock(): void
    {
        if ($this->clock === null)
        {
            $this->clock = new SystemClock();
        }
    }

    protected function InitDatabaseConnection(): void
    {
        if ($this->database === null)
        {
            $this->database = new DataBaseProxy($this->config);
        }

        if ($this->database !== null && $this->connection === null)
        {
            $this->connection = $this->database->GetConnection();
        }
    }

    protected function InitSessionDependencies(): void
    {
        $this->cookie = new SessionCookie(
            $this->config->IsSecure(),
            $this->sessionSettings
        );

        $this->sessionRepo = new AuthSessionRepository($this->connection);

        $this->sessionService = new SessionService(
            $this->sessionRepo,
            $this->clock,
            $this->sessionSettings
        );

        $this->credentialResolver =
        new SessionCredentialResolver($cookie);

        $this->sessionAuthenticator =
        new SessionAuthenticator($this->sessionService, $this->credentialResolver);
    }

    protected function InitRouter(): void
    {
        $this->pipeline = new RequestPipeline($this->sessionAuthenticator);
        $this->router = new Router($this->pipeline);
    }


    /** Route API ------------------------------------ */

    public function AddRoute(
        string $method,
        string $routePath,
        AuthenticationRequirement $authReq,
        IRequestHandler $handler
    ): void {
        $this->CheckCanRoute();
        $this->CheckValidMethod($method);

        $router->Add(
            $method, $routePath,
            new Route($handler, $authReq)
        );
    }

    public function AddGetRoute(
        string $routePath,
        AuthenticationRequirement $authReq,
        IRequestHandler $handler
    ): void {
        $this->AddRoute('GET', $routePath, $authReq, $handler);
    }

    public function AddPostRoute(
        string $routePath,
        AuthenticationRequirement $authReq,
        IRequestHandler $handler
    ): void {
        $this->AddRoute('POST', $routePath, $authReq, $handler);
    }

    public function Dispatch(): void
    {
        $this->CheckCanRoute();

        $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri     = $_SERVER['REQUEST_URI']    ?? '/';
        $cookies = $_COOKIE;
        $headers = array_change_key_case(
            getallheaders() ?: [], CASE_LOWER);

        $reqContext = new RequestContext(
            $method, $uri,
            $headers, $cookies
        );

        $router->Dispatch($context);
    }


    /** Utility ------------------------------------ */

    protected function CheckInitialized(): void {
        if (!$this->isInitialized) { ExceptionHandler::Handle(
            new RuntimeException('Cannot Dispatch Route because UserBase is not initialized')); }
    }

    protected function CheckHasRouter(): void {
        if ($this->router === null) { ExceptionHandler::Handle(
            new RuntimeException('Cannot Add Route because the Router was not found!')); }
    }

    protected function CheckCanRoute(): void {
        $this->CheckInitialized();
        $this->CheckHasRouter();
    }

    protected function CheckValidMethod(string $method): void
    {
        if (!in_array($method, ['GET', 'POST', 'OPTIONS'])) { ExceptionHandler::Handle(
            new RuntimeException("Invalid Method: {$method}!")); }
    }
}

?>