<?php

declare(strict_types=1);

namespace UserBase\Http;

use UserBase\Service\CSRFTokenService;
use UserBase\Security\Authentication\IAuthenticator;
use UserBase\Security\Authentication\SessionCredentialType;

final class RequestPipeline
{
    public function __construct(
        private readonly IAuthenticator $auth,
        private readonly CSRFTokenService $csrfTokenService
    ) {}

    public function Process(
        Route $route,
        RequestContext $reqContext
    ): void {

        // Authenticate request
        $authResult = $this->auth->Authenticate($reqContext);

        // Extract authentication data
        $authContext = $authResult->authContext;
        $credential = $authResult->credential;

        // Attach authentication information to request
        $authenticatedReqContext = $reqContext->WithAuthentication($authContext);

        // Enforce authentication policy
        if (
            $route->policy->authReq === AuthenticationRequirement::Required &&
            !$authContext->IsAuthenticated()
        ) {
            JsonResponse::Error(
                APIErrors::CODE__AUTH_REQUIRED,
                APIErrors::DESC__AUTH_REQUIRED,
                401
            );
        }

        // CSRF Policy
        if (
            $route->policy->requiresCsrf &&
            $authContext->IsAuthenticated() &&
            $authContext->transport === SessionCredentialType::Cookie
        ) {
            $providedToken = $reqContext->GetHeader('x-csrf-token');

            $isInvalid = (
                $credential === null ||
                $credential->type !== SessionCredentialType::Cookie ||
                $providedToken === null ||
                !$this->csrfTokenService->Validate($credential->token, $providedToken)
            );

            if ($isInvalid) {
                JsonResponse::Error(
                    APIErrors::CODE__CSRF_INVALID,
                    APIErrors::DESC__CSRF_INVALID,
                    403
                );
            }
        }

        $route->handler->Execute($authenticatedReqContext);

        /*
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

        $route->handler->Execute($context);
        */
    }
}

?>