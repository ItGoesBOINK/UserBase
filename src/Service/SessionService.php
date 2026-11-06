<?php

declare(strict_types=1);

namespace UserBase\Service;

use UserBase\Repository\AuthSessionRepository;
use UserBase\Time\IClock;
use UserBase\Validation\InputValidator;
use UserBase\Security\TokenUtility;
use UserBase\Security\Authentication\SessionGrant;
use UserBase\Config\SessionSettings;

final class SessionService
{
    public function __construct(
        public readonly AuthSessionRepository $authSessionRepo,
        private readonly IClock $clock,
        private readonly SessionSettings $sessionSettings
    ) {}


    /**
     * Returns the session lifetime in seconds.
     */
    public function GetLifetimeSeconds(): int
    { return $this->sessionSettings->lifetimeSeconds; }


    /**
     * Create a session and return its raw token.
     *
     * The raw token is returned to the caller so it can be
     * delivered in a secure cookie. Only its hash is stored.
     */
    public function Create(string $userID): SessionGrant
    {
        $sessionToken = TokenUtility::NewToken();
        $syncToken = TokenUtility::NewToken();

        $sessionTokenHash = TokenUtility::TokenToHash($sessionToken);
        $syncTokenHash = TokenUtility::TokenToHash($syncToken);

        $now = $this->clock->Now();
        $expiresAt = $now->modify(
            '+' . $this->sessionSettings->lifetimeSeconds . ' seconds'
        );

        $this->authSessionRepo->Create(
            $userID,
            $sessionTokenHash,
            $syncTokenHash,
            $expiresAt
        );

        return new SessionGrant($sessionToken, $syncToken);
    }

    /**
     * Revoke the session represented by a raw token.
     */
    public function Revoke(string $rawToken): void
    {
        if (!InputValidator::IsValidSessionToken($rawToken)) return;

        $this->authSessionRepo->Revoke(
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

        return $this->authSessionRepo->FindActiveByTokenHash(
            TokenUtility::TokenToHash($rawToken),
            $this->clock->Now()
        );
    }

    /**
     * Rotate a CSRF Synchronization Token
     */
    public function RotateSyncToken(string $sessionToken): ?string
    {
        if (!InputValidator::IsValidSessionToken($sessionToken)) { return null; }

        $syncToken = TokenUtility::NewToken();
        $syncTokenHash = TokenUtility::TokenToHash($syncToken);
        $sessionTokenHash = TokenUtility::TokenToHash($sessionToken);

        $updated = $this->authSessionRepo->UpdateSyncTokenHash(
            $sessionTokenHash,
            $syncTokenHash,
            $this->clock->Now()
        );

        return $updated ? $syncToken : null;
    }

    /**
     * Validate a sync token against an active session.
     */
    public function ValidateSyncToken(
        string $sessionToken,
        string $syncToken
    ): bool {
        if (
            !InputValidator::IsValidSessionToken($sessionToken) ||
            !InputValidator::IsValidSessionToken($syncToken)
        ) { return false; }

        return $this->authSessionRepo->HasValidSyncToken(
            TokenUtility::TokenToHash($sessionToken),
            TokenUtility::TokenToHash($syncToken),
            $this->clock->Now()
        );
    }
}