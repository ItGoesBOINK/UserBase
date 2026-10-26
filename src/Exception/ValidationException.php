<?php

declare(strict_types=1);
namespace UserBase\Exception;
use DomainException;

final class ValidationException extends DomainException
{
    public function __construct(string $message)
    { parent::__construct($message); }
}

?>