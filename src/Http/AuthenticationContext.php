<?php

declare(strict_types=1);

namespace UserBase\Http;

final readonly class AuthenticationContext
{
    private function __construct(
        public ?string $userID
    ) {}

    public function IsAuthenticated(): bool
    { return $this->userID !== null; }

    public static function Anonymous(): self
    { return new self(null); }

    public static function Authenticated(string $userID): self
    { return new self($userID); }
}

?>