<?php

declare(strict_types=1);

require_once __DIR__ . '/test_base.php';
require_once __DIR__ . '/TestClock.php';

use DateTimeImmutable;
use DateTimeZone;
use UserBase\Tests\TestClock;
use UserBase\Repository\UserRepository;
use UserBase\Repository\AuthSessionRepository;
use UserBase\Service\SessionService;
use UserBase\Controller\MeController;

$input = json_decode(
    stream_get_contents(STDIN),
    true,
    flags: JSON_THROW_ON_ERROR
);

register_shutdown_function(function (): void {
    $error = error_get_last();

    if ($error !== null) {
        fwrite(
            STDERR,
            '__LAST_ERROR__' . json_encode($error) . $eol
        );
    }
});

$clock = new TestClock(
    new DateTimeImmutable(
        $input['now'],
        new DateTimeZone('UTC')
    )
);

$users = new UserRepository($pdo);
$sessionRepo = new AuthSessionRepository($pdo);
$sessionSvc = new SessionService($sessionRepo, $clock);

if ($input['token'] !== null) {
    $_COOKIE['userbase_session'] = $input['token'];
} else {
    unset($_COOKIE['userbase_session']);
}

$controller = new MeController($sessionSvc, $users);

$controller->Execute();

?>