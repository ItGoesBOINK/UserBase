<?php

declare(strict_types=1);

use UserBase\Core\UserBase;
use UserBase\Http\ExceptionHandler;

try {
    require_once dirname(__DIR__) . '/src/bootstrap.php';
} catch (Throwable $e) {
    ExceptionHandler::Handle($e);
}

try {

    $userBase = new UserBase(
        $config,
        $sessionSettings,
        addDefaultRoutes: true,
        autoInit: true
    );

    $userBase->Dispatch();

} catch (Throwable $e) {
    ExceptionHandler::Handle($e);
}

?>