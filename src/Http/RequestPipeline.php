<?php

declare(strict_types=1);

namespace UserBase\Http;

use RuntimeException;
use DateTimeImmutable;
use UserBase\Time\IClock;
use UserBase\Service\CSRFTokenService;
use UserBase\Service\RateLimitService;
use UserBase\Security\RateLimit\RateLimitManager;
use UserBase\Security\RateLimit\RateLimitDecision;
use UserBase\Security\Authentication\IAuthenticator;
use UserBase\Security\Authentication\SessionCredentialType;

final class RequestPipeline
{
    public function __construct(
        private readonly IAuthenticator $auth,
        private readonly CSRFTokenService $csrfTokenService,
        private readonly RateLimitManager $rateLimitManager,
        private readonly RateLimitService $rateLimitService,
        private readonly IClock $clock
    ) {}

    public function Process(
        Route $route,
        RequestContext $reqContext
    ): void {

        // Enforce Rate-Limits
        $this->EnforceRateLimits($route, $reqContext);

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
    }


    public function CalculateRetryAfter(
        RateLimitDecision $decision,
        DateTimeImmutable $now
    ): int {
        if ($decision->retryAt === null) return 1;

        $seconds = (int)ceil(
            (float)$decision->retryAt->format('U.u') -
            (float)$now->format('U.u')
        );

        return max(1, $seconds);
    }


    private function EnforceRateLimits(
        Route $route,
        RequestContext $reqContext
    ): void {
        $attempts = [];

        foreach ($route->policy->rateLimits as $policyKey) {

            $policyDef = $this->rateLimitManager->FindPolicy($policyKey);

            if ($policyDef === null) {
                throw new RuntimeException("Unknown Rate-Limit Policy: {$policyKey}");
            }

            $resolver = $this->rateLimitManager->FindResolver($policyDef->subjectType);

            if ($resolver === null) {
                throw new RuntimeException("No Resolver registered for subject type: " . $policyDef->subjectType);
            }

            $subject = $resolver->Resolve($reqContext);

            // A missing subject skips this particular policy
            if ($subject === null) { continue; }

            $attempts[] = [
                'policy' => $policyDef->policy,
                'subject' => $subject
            ];
        }

        // No usable subjects means there is nothing to evaluate
        if ($attempts === []) { return; }

        $decisions = $this->rateLimitService->AttemptMultiple($attempts);

        $rateLimited = false;
        $retryAfter = 1;
        $now = $this->clock->Now();

        foreach ($decisions as $decision) {

            if ($decision->allowed) continue;

            $rateLimited = true;

            $retryAfter = max(
                $retryAfter,
                $this->CalculateRetryAfter($decision, $now)
            );

            /*
            if ($decision->retryAt === null) continue;

            $seconds = (int)ceil(
                (float)$decision->retryAt->format('U.u') -
                (float)$now->format('U.u')
            );

            $retryAfter = max($retryAfter, $seconds);
            */
        }

        if (!$rateLimited) return;

        /*
        $retryAt = null;

        foreach ($decisions as $decision) {
            if ($decision->allowed) { continue; }

            if (
                $decision->retryAt !== null &&
                ($retryAt === null || $decision->retryAt > $retryAt)
            ) {
                $retryAt = $decision->retryAt;
            }
        }

        if ($retryAt === null) { return; }

        $retryAfter = max(1, $retryAt->getTimestamp() - $this->clock->Now()->getTimestamp());
        */

        JsonResponse::Error(
            APIErrors::CODE__RATE_LIMITED,
            APIErrors::DESC__RATE_LIMITED,
            429,
            ['Retry-After' => (string)$retryAfter]
        );
    }
}

?>