<?php

declare(strict_types=1);

namespace UserBase\Controller;

use UserBase\Repository\UserRepository;
use UserBase\Http\RequestContext;
use UserBase\Http\APIErrors;
use UserBase\Constants\Names;

final class MeController extends ControllerBase
{
    public function __construct(
        private readonly UserRepository $users
    ) {}

    private function NotifyAuthenticationRequired(): never
    {
        $this->Error(
            APIErrors::CODE__AUTH_REQUIRED,
            APIErrors::DESC__AUTH_REQUIRED,
            401
        );
    }

    private function NotifyUserObtained(array $user): never
    {
        $this->Success([
            'id' => (string) $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ]);
    }

    #[\Override]
    public function Execute(RequestContext $context): void
    {
        // User ID
        $userID = $context->authContext->userID;
        if ($userID === null)
        { $this->NotifyAuthenticationRequired(); }

        // User Object
        $user  = $this->users->FindByID($userID);
        if ($user === null)
        { $this->NotifyAuthenticationRequired(); }

        // Return Authenticated User
        $this->NotifyUserObtained($user);
    }
}

?>