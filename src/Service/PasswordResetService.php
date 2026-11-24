<?php

declare(strict_types=1);

namespace UserBase\Service;

use Throwable;
use RuntimeException;
use UserBase\Http\ExceptionHandler;
use UserBase\Config\Configuration;
use UserBase\Repository\UserRepository;

final class PasswordResetService
{
    public function __construct(
        private readonly Configuration $config,
        private readonly UserRepository $users
    ) {}
}