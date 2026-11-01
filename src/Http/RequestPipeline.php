<?php

declare(strict_types=1);

namespace UserBase\Http;

use UserBase\Security\Authentication\SessionAuthenticator;
use UserBase\Security\Authentication\IAuthenticator;

final class RequestPipeline
{
    public function __construct(
        private readonly IAuthenticator $auth
    ) {}

    public function Process(
        Route $route,
        RequestContext $context
    ): void {
        $auth = $this->auth->Authenticate($context);

        $context = $context->WithAuthentication($auth);

        if (
            $route->policy->authReq === AuthenticationRequirement::Required &&
            !$auth->IsAuthenticated()
        ) {
            JsonResponse::Error(
                APIErrors::CODE__AUTH_REQUIRED,
                APIErrors::DESC__AUTH_REQUIRED,
                401
            );
        }

        $route->controller->Execute($context);
    }
}

?>