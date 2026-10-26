<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Repository\AuthSessionRepository;
use UserBase\Time\IClock;
use UserBase\Validation\InputValidator;
use UserBase\Security\TokenUtility;

final class SessionService
{
    private const int SESSION_LIFETIME_SECONDS = 86400;

    public function __construct(
        private readonly AuthSessionRepository $sessions,
        private readonly IClock $clock
    ) {}

    /**
     * Create a session and return its raw token.
     *
     * The raw token is returned to the caller so it can be
     * delivered in a secure cookie. Only its hash is stored.
     */
    public function Create(string $userID): string
    {
        $rawToken = TokenUtility::NewToken();
        $tokenHash = TokenUtility::TokenToHash($rawToken);

        $now = $this->clock->Now();
        $expiresAt = $now->modify(
            '+' . self::SESSION_LIFETIME_SECONDS . ' seconds'
        );

        $this->sessions->Create(
            $userID,
            $tokenHash,
            $expiresAt
        );

        return $rawToken;
    }

    /**
     * Revoke the session represented by a raw token.
     */
    public function Revoke(string $rawToken): void
    {
        if (!InputValidator::IsValidSessionToken($rawToken)) return;

        $this->sessions->Revoke(
            TokenUtility::TokenToHash($rawToken),
            $this->clock->Now()
        );
    }

    /**
     * Validate a raw session token and return its session data.
     */
    public function Validate(string $rawToken): ?array
    {
        if (!InputValidator::IsValidSessionToken($rawToken)) {
            return null;
        }

        return $this->sessions->FindActiveByTokenHash(
            TokenUtility::TokenToHash($rawToken),
            $this->clock->Now()
        );
    }
}