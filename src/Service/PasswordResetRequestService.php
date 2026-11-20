<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Http\ExceptionHandler;
use RuntimeException;
use Throwable;

final class PasswordResetRequestService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SessionService $sessions
    ) {}
}