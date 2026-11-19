<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use RuntimeException;
use UserBase\Http\RequestContext;
use UserBase\Validation\InputValidator;

final class AccountIdentifierResolver implements IRateLimitSubjectResolver
{
    public function Resolve(RequestContext $reqContext): ?string
    {
        $identifier = $reqContext->body['username'] ?? null;

        if (!is_string($identifier) || trim($identifier) === '')
        {
            return null;
            // throw new RuntimeException('Cannot Resolve Account Identifier');
        }

        return InputValidator::NormalizeUserName($identifier);
    }
}

?>