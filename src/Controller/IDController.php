<?php

declare(strict_types=1);

namespace UserBase\Controller;

use UserBase\Repository\UserRepository;
use UserBase\Http\RequestContext;
use UserBase\Http\APIErrors;
use UserBase\Http\JsonResponse;
use UserBase\Security\Cookies\SessionCookie;
use UserBase\Security\Authentication\SessionCredentialType;
use UserBase\Service\CSRFTokenService;

final class IDController extends ControllerBase
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CSRFTokenService $csrfTokens,
        private readonly SessionCookie $cookie
    ) {
        //echo "IDController::construct ---";
        //echo "CSRF Token Service:\n";
        //var_dump($this->csrfTokens);
        //echo "Cookie:\n";
        //var_dump($this->cookie);
    }

    private function NotifyAuthenticationRequired(): never
    {
        $this->Error(
            APIErrors::CODE__AUTH_REQUIRED,
            APIErrors::DESC__AUTH_REQUIRED,
            401
        );
    }

    private function NotifyUserObtained(
        array $user,
        ?string $csrfToken = null
    ): never
    {
        $response = [
            'id' => (string) $user['id'],
            'username' => $user['username'],
            'email' => $user['email']
        ];

        if ($csrfToken !== null) {
            $response['csrf_token'] = $csrfToken;
        }

        $this->Success($response);
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



        $csrfToken = null;

        //echo "IDController::Execute ---";
        //echo "CSRF Token Service:\n";
        //var_dump($this->csrfTokens);
        //echo "Cookie:\n";
        //var_dump($this->cookie);

        /*
        $response = [
            'csrfTokens' => (string) var_dump($this->csrfTokens),
            'cookie' => (string) var_dump($this->cookie)
        ];
        */

        //JsonResponse::Send($response, 420);
        //return;

        if ($context->authContext->transport === SessionCredentialType::Cookie) {
            $sessionToken = $this->cookie->GetToken($context);

            if ($sessionToken === null) {
                $this->NotifyAuthenticationRequired();
            }

            $csrfToken = $this->csrfTokens->Generate($sessionToken);
        }

        // Return Authenticated User
        $this->NotifyUserObtained($user, $csrfToken);
    }
}

?>