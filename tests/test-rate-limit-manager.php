<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';

use UserBase\Security\RateLimit\RateLimitManager;
use UserBase\Security\RateLimit\RateLimitSubjectType;
use UserBase\Security\RateLimit\ClientIPResolver;
use UserBase\Security\RateLimit\AccountIdentifierResolver;

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

HandleComplete(__FILE__);

?>