<?php

declare(strict_types=1);

namespace UserBase\Service;

use Throwable;
use RuntimeException;
use PDO;
use UserBase\Time\IClock;
use UserBase\Config\Configuration;
use UserBase\Http\ExceptionHandler;
use UserBase\Mail\IEmailSender;
use UserBase\Repository\UserRepository;

final class PasswordResetRequestService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly UserRepository $users
    ) {}
}