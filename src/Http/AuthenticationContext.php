<?php

declare(strict_types=1);

namespace UserBase\Http;
use UserBase\Security\Authentication\SessionCredentialType;

final readonly class AuthenticationContext
{
    private function __construct(
        public ?string $userID,
        public ?SessionCredentialType $transport
    ) {}

    public function IsAuthenticated(): bool
    { return $this->userID !== null; }

    public static function Anonymous(): self
    { return new self(null, null); }

    public static function Authenticated(
        string $userID,
        SessionCredentialType $transport
    ): self
    { return new self($userID, $transport); }
}

?>