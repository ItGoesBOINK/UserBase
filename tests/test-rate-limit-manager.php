<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use InvalidArgumentException;
use UserBase\Http\RequestContext;
use UserBase\Security\RateLimit\RateLimitManager;
use UserBase\Security\RateLimit\RateLimitSubjectType;
use UserBase\Security\RateLimit\RateLimitPolicy;
use UserBase\Security\RateLimit\ClientIPResolver;
use UserBase\Security\RateLimit\AccountIdentifierResolver;
use UserBase\Security\RateLimit\RateLimitPolicyRegistry;
use UserBase\Security\RateLimit\RateLimitSubjectResolverRegistry;
use UserBase\Security\RateLimit\IRateLimitSubjectResolver;

final class TestRateLimitSubjectResolver implements IRateLimitSubjectResolver
{ public function Resolve(RequestContext $reqContext): ?string { return 'test-subject'; } }

function AssertThrows(
    callable $action,
    string $message
): void {
    try {
        $action();
    } catch (InvalidArgumentException $e) {
        AssertTest(true, $message);
        return;
    }

    AssertTest(false, $message);
}

// Initialize

$t_clientIP = RateLimitSubjectType::ClientIP->name;
$t_accountID = RateLimitSubjectType::AccountIdentifier->name;
$k_clientIP = 'login:ip';
$k_accountID = 'login:account';
$manager = new RateLimitManager($config);


// Register Resolvers
$manager->RegisterResolver(
    $t_clientIP,
    new ClientIPResolver()
);

$manager->RegisterResolver(
    $t_accountID,
    new AccountIdentifierResolver()
);

// Register Policies
$manager->AddPolicy($k_clientIP, 10, 300, $t_clientIP);
$manager->AddPolicy($k_accountID, 5, 900, $t_accountID);

// Resolver Lookup
AssertTest(
    $manager->FindResolver($t_clientIP) instanceof ClientIPResolver,
    'Manager finds ClientIPResolver'
);
AssertTest(
    $manager->FindResolver($t_accountID) instanceof AccountIdentifierResolver,
    'Manager finds AccountIdentifierResolver'
);


// Policy Lookup
$ipDef = $manager->FindPolicy($k_clientIP);

AssertTest(
    $ipDef !== null,
    "Manager finds [{$k_clientIP}] Policy"
);

AssertTest(
    $ipDef->policy->maxAttempts === 10 &&
    $ipDef->policy->windowSeconds === 300 &&
    $ipDef->subjectType === $t_clientIP,
    "[{$k_clientIP}] Policy has correct configuration"
);

$accountDef = $manager->FindPolicy($k_accountID);

AssertTest(
    $accountDef !== null,
    "Manager finds [{$k_accountID}] Policy"
);

AssertTest(
    $accountDef->policy->maxAttempts === 5 &&
    $accountDef->policy->windowSeconds === 900 &&
    $accountDef->subjectType === $t_accountID,
    "[{$k_accountID}] Policy has correct configuration"
);


// Unknown Entries

AssertTest(
    $manager->FindPolicy('unknown:policy') === null,
    'Manager returns null for unknown policy'
);

AssertTest(
    $manager->FindResolver('unknown:subject') === null,
    'Manager returns null for unknown resolver'
);

// Invalid Policies

AssertThrows(
    fn() => new RateLimitPolicy('', 10, 300),
    'RateLimitPolicy rejects an empty policy key'
);

AssertThrows(
    fn() => new RateLimitPolicy('login', 0, 300),
    'RateLimitPolicy rejects zero-or-less maximum attempts'
);

AssertThrows(
    fn() => new RateLimitPolicy('login', 10, 0),
    'RateLimitPolicy rejects a zero-length window'
);

// Reject Empty Subject Type
AssertThrows(
    fn() => $manager->policies->Register(
        new RateLimitPolicy('test:empty-subject', 5, 300), ''
    ),
    'Policy Registry rejects an empty Subject-Type'
);

// Reject a whitespace-only subject type
AssertThrows(
    fn() => $manager->policies->Register(
        new RateLimitPolicy('test:whitespace-subject', 5, 300), '   '
    ),
    'Policy Registry rejects an empty Subject-Type'
);

// Reject duplicate policy keys
$manager->policies->Register(
    new RateLimitPolicy('test:duplicate', 5, 300), $t_clientIP
);

AssertThrows(
    fn() => $manager->policies->Register(
        new RateLimitPolicy('test:duplicate', 10, 600), $t_clientIP
    ),
    'Policy Registry rejects an white-space only Subject-Type'
);

// Accepts a valid policy
$manager->policies->Register(
    new RateLimitPolicy('test:valid', 5, 300), $t_clientIP
);

AssertTest(
    $manager->policies->Find('test:valid') !== null,
    'Policy Registry rejects an white-space only Subject-Type'
);

// Reject an empty subject type
AssertThrows(
    fn() => $manager->resolvers->Register(
        '', new TestRateLimitSubjectResolver()
    ),
    'Resolver rejects an empty subject type'
);

// Reject a whitespace only subject type
AssertThrows(
    fn() => $manager->resolvers->Register(
        '   ', new TestRateLimitSubjectResolver()
    ),
    'Resolver rejects an whitespace-only subject type'
);

// Reject duplicate subject types
//$manager->resolvers->Register($t_clientIP, new TestRateLimitSubjectResolver());

AssertThrows(
    fn() => $manager->resolvers->Register(
        $t_clientIP, new TestRateLimitSubjectResolver()
    ),
    'Resolver rejects duplicate subject types'
);


// Reject a missing resolver
AssertThrows(
    fn() => $manager->AddPolicy(
        'test:missing-resolver', 5, 300,
        'MissingResolver'
    ),
    'RateLimitManager rejects policies with un-registered subject types'
);


// Accept a registered resolver
$key = 'test:valid-manager-policy';
$manager->AddPolicy($key, 5, 300, $t_clientIP);

AssertTest(
    $manager->FindPolicy($key) !== null,
    "RateLimitManager accepts policies with registered resolvers"
);


// Reject duplicate policy registration
AssertThrows(
    fn() => $manager->AddPolicy($key, 10, 600, $t_clientIP),
    'RateLimitManager rejects duplicate policy keys'
);

HandleComplete(__FILE__);

?>