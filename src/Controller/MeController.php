<?php

declare(strict_types=1);

namespace UserBase\Controller;

use UserBase\Repository\UserRepository;
use UserBase\Service\SessionService;
use UserBase\Http\RequestContext;

final class MeController extends ControllerBase
{
    private const string SESSION_COOKIE_NAME = 'userbase_session';

    public function __construct(
        private readonly SessionService $sessions,
        private readonly UserRepository $users
    ) {}

    private function NotifyAuthenticationRequired(): never
    {
        $this->Error(
            'unauthorized',
            'Authentication is Required',
            401
        );
    }

    #[\Override]
    public function Execute(RequestContext $context): void
    {
        // Token
        $token = $_COOKIE[self::SESSION_COOKIE_NAME] ?? null;
        if (!is_string($token))
        { $this->NotifyAuthenticationRequired(); }

        // Session
        $session = $this->sessions->Validate($token);
        if ($session === null)
        { $this->NotifyAuthenticationRequired(); }

        // User
        $user = $this->users->FindByID((string) $session['user_id']);
        if ($user === null)
        { $this->NotifyAuthenticationRequired(); }

        // Return Authenticated User
        $this->Success([
            'id' => (string) $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]);
    }
}






?>